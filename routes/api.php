<?php

use App\Http\Controllers\Api\AkunController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\InformasiController;
use App\Http\Controllers\Api\KonselingController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RiwayatKelasController;
use App\Http\Controllers\Api\SiswaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — BK System (Laravel port)
| Prefix: /api  (otomatis dari bootstrap)
| Kompatibel dengan frontend React yang memakai Authorization: Bearer <token>
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'account.active', 'password.changed'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Siswa — melihat daftar siswa dibutuhkan staff (guru/kepsek/admin) untuk
    // keperluan konseling & monitoring.
    Route::middleware('ability:guru,kepsek,admin')->group(function () {
        Route::get('/siswa', [SiswaController::class, 'list']);
        Route::get('/riwayat-kelas/{nis}/aktif', [RiwayatKelasController::class, 'getAktif']);
        Route::get('/riwayat-kelas/{nis}', [RiwayatKelasController::class, 'list']);
    });
    Route::middleware('ability:guru,admin')->group(function () {
        Route::post('/siswa', [SiswaController::class, 'create']);
        Route::post('/siswa/import-rows', [SiswaController::class, 'importRows']);
    });
    Route::middleware('ability:admin')->group(function () {
        Route::post('/riwayat-kelas', [RiwayatKelasController::class, 'create']);
        Route::delete('/riwayat-kelas/{id}', [RiwayatKelasController::class, 'remove']);
    });

    // Profil
    Route::get('/profile/{nis}', [ProfileController::class, 'get']);
    Route::put('/profile/{nis}', [ProfileController::class, 'update']);
    Route::put('/profile/{nis}/foto', [ProfileController::class, 'updateFoto']);
    Route::delete('/profile/{nis}/foto', [ProfileController::class, 'deleteFoto']);

    // Informasi BK
    Route::get('/informasi', [InformasiController::class, 'list']);
    Route::post('/informasi', [InformasiController::class, 'create']);
    Route::put('/informasi/{id}', [InformasiController::class, 'update']);
    Route::delete('/informasi/{id}', [InformasiController::class, 'remove']);

    // Konseling
    Route::get('/konseling-all', [KonselingController::class, 'listAll']);
    Route::get('/konseling-bk', [KonselingController::class, 'listByGuru']);
    Route::get('/konseling/detail/{id}', [KonselingController::class, 'getDetail']);
    Route::get('/konseling/{nis}', [KonselingController::class, 'listBySiswa']);
    Route::middleware('ability:siswa')->post('/konseling', [KonselingController::class, 'store']);
    Route::post('/konseling/walkin', [KonselingController::class, 'walkin']);
    Route::put('/konseling/{id}/konfirmasi', [KonselingController::class, 'konfirmasi']);
    Route::put('/konseling/{id}/laporan', [KonselingController::class, 'laporan']);
    Route::put('/konseling/{id}/status', [KonselingController::class, 'updateStatus']);

    // Notifikasi
    Route::get('/notifikasi', [NotifikasiController::class, 'list']);
    Route::put('/notifikasi/{id}/read', [NotifikasiController::class, 'markRead']);
    Route::put('/notifikasi/read-all', [NotifikasiController::class, 'markAllRead']);
    Route::post('/push/subscribe', [NotifikasiController::class, 'subscribe']);

    // Chat (HTTP history + AI; real-time tetap bisa pakai Socket.IO Node terpisah)
    Route::get('/chat/history', [ChatController::class, 'history']);
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/chat', [ChatController::class, 'ai']); // kompatibel React: POST /api/chat
    Route::post('/chat/ai', [ChatController::class, 'ai']);

    // Akun (admin) — hanya Admin boleh membuat/mengubah/menonaktifkan akun
    // Guru BK & Kepala Sekolah.
    Route::middleware('ability:admin')->group(function () {
        Route::get('/akun/guru', [AkunController::class, 'listGuru']);
        Route::post('/akun/guru', [AkunController::class, 'createGuru']);
        Route::put('/akun/guru/{id}', [AkunController::class, 'updateGuru']);
        Route::delete('/akun/guru/{id}', [AkunController::class, 'deleteGuru']);
        Route::get('/akun/kepsek', [AkunController::class, 'listKepsek']);
        Route::post('/akun/kepsek', [AkunController::class, 'createKepsek']);
        Route::put('/akun/kepsek/{id}', [AkunController::class, 'updateKepsek']);
        Route::delete('/akun/kepsek/{id}', [AkunController::class, 'deleteKepsek']);
    });
});
