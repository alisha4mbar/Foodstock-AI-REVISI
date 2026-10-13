<?php

namespace App\Http\Controllers;

use App\Http\Presenter;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** FR-01 manajemen pengguna (Admin). */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->orderByDesc('id');
        if ($s = $request->query('status')) $q->where('status', $s);

        return response()->json(Presenter::paginated($q, $request, [Presenter::class, 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,dinas,bulog,distributor',
            'wilayah_id' => 'nullable|exists:wilayah,id',
            'instansi' => 'nullable|string|max:255',
            'password' => ['required', Password::min(8)],
        ]);
        $user = User::create($data + ['status' => 'aktif']);
        AuditService::log("Menambah pengguna {$user->email}", 'users', $user->id, null, ['role' => $user->role]);

        return response()->json(['data' => Presenter::user($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'role' => 'sometimes|in:admin,dinas,bulog,distributor',
            'wilayah_id' => 'sometimes|nullable|exists:wilayah,id',
            'instansi' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|in:aktif,pending,ditolak,terkunci,nonaktif',
        ]);
        if ($user->id === $request->user()->id && (isset($data['role']) || isset($data['status']))) {
            return response()->json(['code' => 'FORBIDDEN', 'message' => 'Anda tidak dapat mengubah role/status akun sendiri.', 'errors' => null], 403);
        }
        $old = $user->only(array_keys($data));
        if (($data['status'] ?? null) === 'aktif') $data['failed_attempts'] = 0;
        $user->update($data);
        AuditService::log("Mengubah pengguna {$user->email}", 'users', $user->id, $old, $data);

        return response()->json(['data' => Presenter::user($user->fresh())]);
    }

    public function verify(Request $request, User $user): JsonResponse
    {
        $approve = $request->validate(['approve' => 'required|boolean'])['approve'];
        $user->update(['status' => $approve ? 'aktif' : 'ditolak']);
        AuditService::log(($approve ? 'Menyetujui' : 'Menolak')." registrasi {$user->email}", 'users', $user->id);

        return response()->json(['data' => Presenter::user($user)]);
    }

    public function toggleLock(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['code' => 'FORBIDDEN', 'message' => 'Anda tidak dapat mengunci akun sendiri.', 'errors' => null], 403);
        }
        $locking = $user->status !== 'terkunci';
        $user->update(['status' => $locking ? 'terkunci' : 'aktif', 'failed_attempts' => 0]);
        if ($locking) $user->tokens()->delete();
        AuditService::log(($locking ? 'Mengunci' : 'Membuka kunci')." akun {$user->email}", 'users', $user->id);

        return response()->json(['data' => Presenter::user($user)]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['code' => 'FORBIDDEN', 'message' => 'Anda tidak dapat menghapus akun sendiri.', 'errors' => null], 403);
        }
        AuditService::log("Menghapus pengguna {$user->email}", 'users', $user->id);
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Pengguna dihapus.']);
    }
}
