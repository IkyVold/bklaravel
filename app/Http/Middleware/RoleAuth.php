<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class RoleAuth
{
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

        // PERBAIKAN (revisi 25 Agustus 2026, poin 11): siswa yang akunnya
        // masih ditandai must_change_password (password default = NIS,
        // atau baru saja direset Admin) tidak boleh mengakses halaman lain
        // sebelum mengganti password-nya sendiri. Hanya halaman profil
        // (tempat form ganti password berada) dan logout yang dikecualikan
        // — logout sudah berada di luar grup middleware 'role:siswa' ini,
        // jadi tidak perlu disebut di whitelist. Guru/Kepsek/Admin TIDAK
        // terkena pengecekan ini: mereka belum punya mekanisme self-service
        // ganti password sama sekali, jadi memblokir mereka di sini hanya
        // akan mengunci akun tanpa jalan keluar.
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
