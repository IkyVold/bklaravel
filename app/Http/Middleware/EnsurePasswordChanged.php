<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\Siswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PERBAIKAN (revisi 25 Agustus 2026, poin 11): sebelumnya tidak ada
 * mekanisme apa pun yang memaksa siswa mengganti password default
 * (password awal siswa SELALU = NIS sendiri — lihat Api/SiswaController@
 * create/@importRows — dan NIS bukan rahasia, sering tertera di kartu
 * pelajar/absensi/rapor). Selama siswa tidak pernah mengganti password,
 * akun tetap bisa diakses siapa pun yang tahu NIS-nya.
 *
 * Middleware ini mengunci SELURUH endpoint API untuk siswa yang akunnya
 * masih ditandai must_change_password = true, KECUALI endpoint yang
 * dibutuhkan siswa untuk benar-benar mematuhi kewajiban ini (lihat
 * ganti password diri sendiri) dan endpoint dasar (logout, cek sesi).
 *
 * Guru BK, Kepsek, dan Admin TIDAK terpengaruh middleware ini — mereka
 * belum memiliki mekanisme self-service ganti password sama sekali
 * (akun mereka hanya bisa direset oleh Admin lewat AkunController), jadi
 * memblokir mereka di sini hanya akan mengunci akun tanpa jalan keluar.
 * Penerapan mekanisme wajib-ganti-password untuk staff perlu menunggu
 * fitur ganti password self-service untuk staff dibuat terlebih dahulu.
 */
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
