<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use App\Services\AuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function __construct(private AuthenticationService $auth)
    {
    }

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
        // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu cuma
        // 'string' — halaman login sudah menjanjikan "NIS harus 4 digit
        // angka" tapi backend menerima string apa pun. Sekarang
        // diseragamkan dengan aturan NIS di seluruh sistem.
        $request->validate(['nis' => 'required|digits:4']);
        $nis = $request->input('nis');

        // PERBAIKAN (revisi 26 Agustus 2026, poin 2): limiter global per-IP
        // dicek lebih dulu — identik dengan jalur API — supaya satu IP
        // tidak bisa lolos batas percobaan hanya dengan mengganti-ganti NIS.
        $ipKey = $this->auth->ipThrottleKey($request);
        if ($this->auth->tooManyIpAttempts($ipKey)) {
            $seconds = $this->auth->ipAvailableIn($ipKey);
            return back()->withInput()->withErrors([
                'login' => "Terlalu banyak percobaan login dari jaringan ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Burst throttle — identik dengan jalur API supaya login web tidak
        // bisa dipakai sebagai jalur brute force tanpa batas.
        $throttleKey = $this->auth->throttleKey('siswa', $nis, $request);
        if ($this->auth->tooManyAttempts($throttleKey)) {
            $seconds = $this->auth->availableIn($throttleKey);
            return back()->withInput()->withErrors([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            $this->auth->hitThrottle($throttleKey);
            $this->auth->hitIpThrottle($ipKey);
            return back()->withInput()->withErrors(['login' => 'NIS atau password salah.']);
        }

        // Akun yang terkunci lewat API (locked_until) HARUS ikut ditolak di
        // sini juga — sebelumnya jalur web tidak memeriksa kolom ini sama
        // sekali sehingga lockout bisa dilewati hanya dengan login lewat web.
        if ($this->auth->isSiswaLocked($siswa)) {
            $jam = $siswa->locked_until->timezone('Asia/Jakarta')->format('d M Y H:i');
            return back()->withInput()->withErrors([
                'login' => "Akun terkunci sementara karena terlalu banyak percobaan login gagal. Coba lagi setelah {$jam} WIB.",
            ]);
        }

        if (!$siswa->verifyPassword($request->password)) {
            $this->auth->hitThrottle($throttleKey);
            $this->auth->hitIpThrottle($ipKey);
            $this->auth->registerSiswaFailure($siswa);

            if ($this->auth->isSiswaLocked($siswa)) {
                $jam = $siswa->locked_until->timezone('Asia/Jakarta')->format('d M Y H:i');
                return back()->withInput()->withErrors([
                    'login' => "Akun dikunci sementara karena beberapa kali login gagal. Coba lagi setelah {$jam} WIB.",
                ]);
            }

            return back()->withInput()->withErrors(['login' => 'NIS atau password salah.']);
        }

        $this->auth->clearThrottle($throttleKey);
        $this->auth->resetSiswaAttempts($siswa);

        Session::put('auth_role', 'siswa');
        Session::put('auth_id', $siswa->id);
        Session::put('auth_user', [
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'foto' => $siswa->foto_profile,
            // PERBAIKAN (revisi 25 Agustus 2026, poin 11): disimpan di
            // session supaya RoleAuth middleware bisa memeriksanya tanpa
            // query DB tambahan di setiap request.
            'must_change_password' => (bool) $siswa->must_change_password,
        ]);
        // PERBAIKAN (revisi 27 Agustus 2026, poin 2): baseline
        // password_version disimpan sama seperti loginStaff() di bawah —
        // dibaca SETELAH verifyPassword() di atas selesai (termasuk
        // kemungkinan upgrade hash lama md5->bcrypt yang ikut menaikkan
        // versi ini), supaya baseline yang tersimpan selalu mencerminkan
        // state password PALING BARU saat login ini terjadi. RoleAuth
        // membandingkan ulang nilai ini ke database pada setiap request;
        // kalau Admin mereset password siswa setelah session ini dibuat,
        // versi di database naik dan tidak lagi cocok dengan baseline
        // ini, sehingga session lama langsung dipaksa logout.
        Session::put('auth_password_version', (int) $siswa->password_version);
        Session::regenerate();

        if ($siswa->must_change_password) {
            return redirect()->route('siswa.profil')
                ->with('error', 'Ini pertama kali Anda login (atau password Anda baru direset). Silakan ganti password default Anda terlebih dahulu.');
        }

        return redirect()->route('siswa.dashboard');
    }

    private function loginStaff(Request $request, string $model, string $role)
    {
        $request->validate(['username' => 'required|string']);
        $username = $request->input('username');

        $table = (new $model)->getTable();
        if (!Schema::hasTable($table)) {
            return back()->withInput()->withErrors(['login' => "Tabel {$table} belum ada."]);
        }

        // PERBAIKAN (revisi 26 Agustus 2026, poin 2): limiter global per-IP
        // dicek lebih dulu, sama seperti loginSiswa() dan jalur API.
        $ipKey = $this->auth->ipThrottleKey($request);
        if ($this->auth->tooManyIpAttempts($ipKey)) {
            $seconds = $this->auth->ipAvailableIn($ipKey);
            return back()->withInput()->withErrors([
                'login' => "Terlalu banyak percobaan login dari jaringan ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Guru/Kepsek/Admin belum punya kolom lockout persisten, tetapi
        // tetap dilindungi burst throttle yang sama dengan API supaya
        // keamanan kedua jalur konsisten.
        $throttleKey = $this->auth->throttleKey($role, $username, $request);
        if ($this->auth->tooManyAttempts($throttleKey)) {
            $seconds = $this->auth->availableIn($throttleKey);
            return back()->withInput()->withErrors([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $user = $model::where('username', $username)->first();
        if (!$user || !($user->is_active ?? true) || !$user->verifyPassword($request->password)) {
            $this->auth->hitThrottle($throttleKey);
            $this->auth->hitIpThrottle($ipKey);
            return back()->withInput()->withErrors(['login' => 'Username atau password salah.']);
        }

        $this->auth->clearThrottle($throttleKey);

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
        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): simpan
        // password_version saat ini sebagai baseline session. Dibaca
        // $user->password_version SETELAH verifyPassword() di atas
        // selesai (termasuk kemungkinan upgrade hash lama md5->bcrypt,
        // yang ikut menaikkan versi ini), supaya baseline yang tersimpan
        // selalu mencerminkan state password yang PALING BARU saat login
        // ini terjadi — bukan state sebelum request ini. RoleAuth
        // membandingkan ulang nilai ini ke database pada setiap request;
        // kalau sudah tidak cocok berarti password diganti setelah
        // session ini dibuat, dan session langsung dipaksa logout.
        Session::put('auth_password_version', (int) $user->password_version);
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
