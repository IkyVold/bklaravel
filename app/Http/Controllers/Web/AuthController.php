<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Session::has('auth_role')) {
            return redirect()->route($this->homeRoute(Session::get('auth_role')));
        }
        return view('auth.login');
    }

    public function showLoginRole(string $role)
    {
        if (!in_array($role, ['siswa', 'guru', 'kepsek', 'admin'], true)) {
            abort(404);
        }
        return view('auth.login-role', compact('role'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'role' => 'required|in:siswa,guru,kepsek,admin',
            'password' => 'required|string',
        ]);

        return match ($request->input('role')) {
            'siswa' => $this->loginSiswa($request),
            'guru' => $this->loginStaff($request, GuruBk::class, 'guru'),
            'kepsek' => $this->loginStaff($request, Kepsek::class, 'kepsek'),
            'admin' => $this->loginStaff($request, Admin::class, 'admin'),
        };
    }

    private function loginSiswa(Request $request)
    {
        $request->validate(['nis' => 'required|string']);

        $siswa = Siswa::where('nis', $request->nis)->first();
        if (!$siswa || !$siswa->verifyPassword($request->password)) {
            return back()->withInput()->withErrors(['login' => 'NIS atau password salah.']);
        }

        // Reset kunci jika kolom ada (tidak mengubah data lain)
        if (Schema::hasColumn('siswa', 'failed_login_attempts')) {
            $siswa->failed_login_attempts = 0;
            $siswa->locked_until = null;
            $siswa->save();
        }

        Session::put('auth_role', 'siswa');
        Session::put('auth_id', $siswa->id);
        Session::put('auth_user', [
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'foto' => $siswa->foto_profile,
        ]);
        Session::regenerate();

        return redirect()->route('siswa.dashboard');
    }

    private function loginStaff(Request $request, string $model, string $role)
    {
        $request->validate(['username' => 'required|string']);

        $table = (new $model)->getTable();
        if (!Schema::hasTable($table)) {
            return back()->withInput()->withErrors(['login' => "Tabel {$table} belum ada."]);
        }

        $user = $model::where('username', $request->username)->first();
        if (!$user || !($user->is_active ?? true) || !$user->verifyPassword($request->password)) {
            return back()->withInput()->withErrors(['login' => 'Username atau password salah.']);
        }

        $extra = [
            'username' => $user->username,
            'nama' => $user->nama,
        ];
        if ($role === 'guru') {
            $extra['foto'] = $user->foto_profile ?? null;
            $extra['spesialisasi'] = $user->spesialisasi ?? null;
            $extra['npsn'] = $user->npsn ?? null;
        }
        if ($role === 'kepsek') {
            $extra['npsn'] = $user->npsn ?? null;
            $extra['sekolah'] = $user->sekolah ?? null;
        }

        Session::put('auth_role', $role);
        Session::put('auth_id', $user->id);
        Session::put('auth_user', $extra);
        Session::regenerate();

        return redirect()->route($this->homeRoute($role));
    }

    public function logout(Request $request)
    {
        Session::flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Anda telah keluar.');
    }

    private function homeRoute(string $role): string
    {
        return match ($role) {
            'siswa' => 'siswa.dashboard',
            'guru' => 'guru.dashboard',
            'kepsek' => 'kepsek.dashboard',
            'admin' => 'admin.dashboard',
            default => 'login',
        };
    }
}
