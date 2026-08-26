<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Action controller yang tetap boleh diakses walau must_change_password
     * masih true. Siswa HARUS bisa mencapai endpoint ini untuk mengganti
     * password-nya sendiri (ProfileController@get/@update) dan untuk
     * operasi dasar sesi (logout, me).
     */
    private const EXEMPT_ACTIONS = [
        ProfileController::class . '@get',
        ProfileController::class . '@update',
        AuthController::class . '@logout',
        AuthController::class . '@me',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Siswa || !$user->must_change_password) {
            return $next($request);
        }

        $action = $request->route()?->getActionName();
        if (in_array($action, self::EXEMPT_ACTIONS, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Anda wajib mengganti password default terlebih dahulu. Gunakan PUT /api/profile/{nis} dengan field password untuk menggantinya.',
            'must_change_password' => true,
        ], 423);
    }
}
