<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang belum diverifikasi Admin (status pending) hanya boleh /auth/me dan /auth/logout. */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->status !== 'aktif') {
            return response()->json([
                'code' => 'ACCOUNT_PENDING',
                'message' => 'Akun Anda belum diverifikasi Administrator Utama.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
