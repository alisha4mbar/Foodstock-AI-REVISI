<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const BLOCKED = [
        // 'pending' tidak diblokir: pengguna boleh masuk ke halaman tunggu verifikasi (/menunggu).
        'ditolak' => ['ACCOUNT_REJECTED', 'Registrasi akun Anda ditolak. Hubungi Administrator.'],
        'terkunci' => ['ACCOUNT_LOCKED', 'Akun terkunci karena terlalu banyak percobaan gagal. Hubungi Administrator.'],
        'nonaktif' => ['ACCOUNT_INACTIVE', 'Akun Anda tidak aktif. Hubungi Administrator.'],
    ];

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $data['email'] = strtolower(trim($data['email']));
        $user = User::where('email', $data['email'])->first();

        // Email Admin Utama: login pertama otomatis membuat akun admin (password = yang diketik),
        // login berikutnya normal. Akun lama beremail ini dinaikkan menjadi admin bila password cocok.
        if ($this->isAdminEmail($data['email'])) {
            if (! $user) {
                $request->validate(['password' => [Password::min(8)]]);
                $user = User::create([
                    'name' => 'Administrator Utama', 'email' => $data['email'], 'password' => $data['password'],
                    'role' => 'admin', 'instansi' => 'Administrator Sistem', 'status' => 'aktif',
                ]);
                AuditService::log('Akun Admin Utama dibuat otomatis saat login pertama', 'users', $user->id);
            } elseif ($user->role !== 'admin' && Hash::check($data['password'], $user->password)) {
                $user->forceFill(['role' => 'admin', 'status' => 'aktif', 'failed_attempts' => 0])->save();
            }
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Hitung percobaan gagal hanya untuk akun aktif; kunci setelah batas tercapai.
            if ($user && $user->status === 'aktif') {
                $user->failed_attempts++;
                if ($user->failed_attempts >= config('foodstock.login_max_attempts')) {
                    $user->status = 'terkunci';
                }
                $user->save();
            }
            // Pesan sama untuk email tidak terdaftar dan password salah (E-02, E-03).
            return response()->json(['code' => 'INVALID_CREDENTIALS', 'message' => 'Email atau password salah.', 'errors' => null], 401);
        }

        if (isset(self::BLOCKED[$user->status])) {
            [$code, $msg] = self::BLOCKED[$user->status];
            return response()->json(['code' => $code, 'message' => $msg, 'errors' => null], 403);
        }

        $user->forceFill(['failed_attempts' => 0])->save();
        $token = $user->createToken('web')->plainTextToken;
        auth()->setUser($user);
        AuditService::log('Login ke sistem', 'users', $user->id);

        return response()->json(['data' => ['token' => $token, 'token_type' => 'Bearer', 'user' => Presenter::user($user)]]);
    }

    private function isAdminEmail(string $email): bool
    {
        return in_array(strtolower($email), config('foodstock.admin_emails', []), true);
    }

    /** Registrasi publik: akun berstatus pending sampai diverifikasi Admin (FR-01). Role TIDAK dipilih pengguna: Admin Utama yang memberi role (dinas/bulog/distributor) saat memverifikasi. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'instansi' => 'required|string|min:2|max:255',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $data['email'] = strtolower(trim($data['email']));

        if ($this->isAdminEmail($data['email'])) {
            return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'Data yang dikirim tidak valid.',
                'errors' => ['email' => ['Email ini tidak dapat didaftarkan.']]], 422);
        }

        // role sementara hanya placeholder; akun pending tidak bisa login sampai Admin memberi role & memverifikasi.
        $user = User::create($data + ['role' => 'distributor', 'status' => 'pending']);

        return response()->json(['data' => Presenter::user($user)], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => Presenter::user($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|min:3|max:255', 'instansi' => 'required|string|min:2|max:255']);
        $request->user()->update($data);

        return response()->json(['data' => Presenter::user($request->user()->fresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'Data yang dikirim tidak valid.',
                'errors' => ['current_password' => ['Password saat ini salah.']]], 422);
        }

        $user->update(['password' => $data['new_password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}
