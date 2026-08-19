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

        // Share auth data with all views
        view()->share('authRole', $role);
        view()->share('authUser', Session::get('auth_user', []));
        view()->share('authId', Session::get('auth_id'));

        return $next($request);
    }
}
