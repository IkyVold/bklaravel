<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && array_key_exists('is_active', $user->getAttributes()) && !$user->is_active) {
            // Bersihkan seluruh token milik akun ini supaya percobaan
            // berikutnya ditolak lebih awal oleh auth:sanctum sendiri.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Akun ini sudah dinonaktifkan.',
            ], 403);
        }

        return $next($request);
    }
}
