<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
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

        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): sebelumnya middleware
        // ini HANYA mempercayai role/identitas yang tersimpan di session,
        // tanpa pernah mengecek ulang ke database apakah akun tsb masih
        // aktif. Akibatnya Guru BK/Kepsek/Admin yang BARU SAJA dinonaktifkan
        // Admin tetap bisa terus memakai session web-nya yang lama sampai
        // session itu kedaluwarsa sendiri — menonaktifkan akun jadi tidak
        // benar-benar langsung berlaku.
        //
        // Query kecil ini dijalankan setiap request untuk role guru/kepsek/
        // admin (siswa dilewati karena tidak punya kolom is_active). Kalau
        // akun sudah tidak aktif atau bahkan sudah dihapus, session langsung
        // dibersihkan dan pengguna dipaksa login ulang — yang tentu akan
        // ditolak juga di AuthController karena is_active sudah false.
        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): sebelumnya reset
        // password Guru BK/Kepsek/Admin lewat Admin hanya mencabut token
        // Sanctum (API) — session Web yang sudah terlanjur login tetap
        // hidup penuh, karena middleware ini cuma memeriksa akun
        // ada/is_active, bukan apakah password berubah setelah session
        // dibuat. Sekarang query yang sama ($user di atas) juga dipakai
        // untuk membandingkan password_version akun saat ini dengan
        // baseline yang disimpan di session saat login (lihat
        // Web\AuthController@loginStaff). Kalau tidak cocok lagi —
        // artinya password sudah diganti setelah session ini dibuat —
        // session langsung dipaksa logout, persis seperti perlakuan
        // is_active di atas.
        $authId = Session::get('auth_id');
        $modelClass = self::ROLE_MODELS[$role] ?? null;
        $user = null;
        if ($modelClass && $authId) {
            $user = $modelClass::find($authId);
            if (!$user || !$user->is_active) {
                Session::flush();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->withErrors(['login' => 'Akun ini sudah dinonaktifkan. Silakan hubungi Admin.']);
            }

            // PERBAIKAN: baseline hanya dibandingkan kalau session ini
            // memang membawanya (artinya session dibuat lewat alur login
            // yang sudah diperbarui). Session lama yang dibuat SEBELUM
            // fitur ini ada tidak pernah menyimpan kunci ini sama sekali
            // — bukan berarti passwordnya berubah, jadi tidak dipaksa
            // logout hanya karena kunci ini belum ada. exists() dipakai
            // (bukan has()) supaya baseline bernilai null tetap dianggap
            // "ada dan tercatat", bukan disamakan dengan "tidak pernah
            // disimpan".
            //
            // Dibandingkan sebagai COUNTER (password_version), bukan
            // timestamp: timestamp cuma presisi detik, jadi login dan
            // reset password yang terjadi dalam detik yang sama berisiko
            // menghasilkan nilai yang SAMA dan gagal terdeteksi sebagai
            // perubahan. Counter integer yang selalu naik tidak punya
            // risiko tabrakan seperti itu.
            if (Session::exists('auth_password_version')) {
                $currentVersion = (int) $user->password_version;
                $sessionVersion = (int) Session::get('auth_password_version');
                if ($currentVersion !== $sessionVersion) {
                    Session::flush();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')
                        ->withErrors(['login' => 'Password akun ini baru saja diubah. Silakan login ulang.']);
                }
            }
        }

        // PERBAIKAN (revisi 27 Agustus 2026, poin 4): sebelumnya nilai
        // must_change_password yang dipakai di sini diambil dari
        // Session::get('auth_user')['must_change_password'] — yaitu
        // SNAPSHOT yang ditulis satu kali saat siswa login (lihat
        // Web\AuthController@loginStaff/siswa login). Snapshot itu tidak
        // pernah diperbarui selama sesi siswa masih hidup. Akibatnya:
        //   1. Siswa login → snapshot must_change_password = false.
        //   2. Admin lalu mereset password siswa tsb → kolom di DB
        //      berubah jadi true.
        //   3. Session lama siswa TIDAK ikut terpengaruh: snapshot di
        //      langkah 1 masih false, jadi siswa tetap bebas mengakses
        //      semua halaman seolah tidak pernah direset — gate wajib
        //      ganti password ini jadi tidak efektif untuk sesi yang
        //      sudah berjalan.
        //
        // Sekarang, sama seperti perlakuan is_active untuk Guru/Kepsek/
        // Admin di atas, record siswa dibaca ULANG dari database pada
        // SETIAP request supaya nilai must_change_password yang dipakai
        // selalu yang terbaru, bukan snapshot lama. Kalau akunnya sudah
        // tidak ada (dihapus), sesi langsung dipaksa logout — konsisten
        // dengan perlakuan akun nonaktif Guru/Kepsek/Admin.
        if ($role === 'siswa') {
            $siswa = Siswa::find($authId);

            if (!$siswa) {
                Session::flush();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->withErrors(['login' => 'Akun ini sudah tidak ditemukan. Silakan hubungi Admin.']);
            }

            // Sinkronkan snapshot di session supaya bagian lain aplikasi
            // yang masih membaca Session::get('auth_user') (mis. header/
            // partial view) juga melihat nilai terbaru, bukan nilai basi
            // dari saat login.
            $authUser = Session::get('auth_user', []);
            $authUser['must_change_password'] = (bool) $siswa->must_change_password;
            Session::put('auth_user', $authUser);

            $exemptRoutes = ['siswa.profil', 'siswa.profil.update'];
            if ($siswa->must_change_password && !in_array($request->route()?->getName(), $exemptRoutes, true)) {
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
