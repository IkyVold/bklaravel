<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class RoleAuth
{
    /**
     * Peta role -> model, khusus role yang punya kolom is_active
     * (Guru BK, Kepsek, Admin). Siswa sengaja tidak disertakan karena
     * tidak memiliki kolom is_active sama sekali.
     */
    private const ROLE_MODELS = [
        'guru' => GuruBk::class,
        'kepsek' => Kepsek::class,
        'admin' => Admin::class,
    ];

    /**
     * @param  string  ...$roles  Allowed roles, e.g. 'guru','admin'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = Session::get('auth_role');

        if (!$role) {
            return redirect()->route('login')->withErrors(['login' => 'Silakan login terlebih dahulu.']);
        }

        if (!empty($roles) && !in_array($role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        $authId = Session::get('auth_id');
        $modelClass = self::ROLE_MODELS[$role] ?? null;
        if ($modelClass && $authId) {
            $user = $modelClass::find($authId);
            if (!$user || !$user->is_active) {
                Session::flush();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->withErrors(['login' => 'Akun ini sudah dinonaktifkan. Silakan hubungi Admin.']);
            }
        }

        if ($role === 'siswa') {
            $authUser = Session::get('auth_user', []);
            $exemptRoutes = ['siswa.profil', 'siswa.profil.update'];
            if (!empty($authUser['must_change_password']) && !in_array($request->route()?->getName(), $exemptRoutes, true)) {
                return redirect()->route('siswa.profil')
                    ->with('error', 'Anda wajib mengganti password default sebelum melanjutkan.');
            }
        }

        // Share auth data with all views
        view()->share('authRole', $role);
        view()->share('authUser', Session::get('auth_user', []));
        view()->share('authId', Session::get('auth_id'));

        return $next($request);
    }
}
