<?php

use App\Http\Controllers\Web\AkunController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\KepsekController;
use App\Http\Controllers\Web\InformasiController;
use App\Http\Controllers\Web\KonselingController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\NotifikasiWebController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SiswaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login.role', ['role' => 'siswa']));

Route::get('/login', fn () => redirect()->route('login.role', ['role' => 'siswa']))->name('login');
Route::get('/login/{role}', [AuthController::class, 'showLoginRole'])->name('login.role');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'siswa'])->name('dashboard');
    Route::get('/konseling', [KonselingController::class, 'indexSiswa'])->name('konseling.index');
    Route::get('/konseling/ajukan', [KonselingController::class, 'createSiswa'])->name('konseling.create');
    Route::post('/konseling', [KonselingController::class, 'storeSiswa'])->name('konseling.store');
    Route::get('/status', [KonselingController::class, 'statusIndex'])->name('status.index');
    Route::get('/chat/{konselingId}', [ChatController::class, 'room'])->name('chat');
    Route::post('/chat/{konselingId}', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{konselingId}/messages', [ChatController::class, 'historyJson'])->name('chat.messages');
    Route::post('/ai-faq', [ChatController::class, 'ai'])->name('ai.faq');
    Route::post('/notifikasi/read-all', [NotifikasiWebController::class, 'readAll'])->name('notifikasi.readAll');

    Route::get('/status/{id}', [KonselingController::class, 'statusSiswa'])->name('status');
    Route::delete('/konseling/{id}', [KonselingController::class, 'destroySiswa'])->name('konseling.destroy');
    Route::post('/konseling/{id}/batal', [KonselingController::class, 'batalSiswa'])->name('konseling.batal');
    Route::get('/konseling/{id}', [KonselingController::class, 'show'])->name('konseling.show');
    Route::get('/informasi', [InformasiController::class, 'index'])->name('informasi');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
});

Route::middleware('role:guru')->prefix('guru')->name('guru.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'guru'])->name('dashboard');
    Route::get('/konseling', [KonselingController::class, 'indexGuru'])->name('konseling.index');
    Route::get('/konseling/walkin', [KonselingController::class, 'walkinForm'])->name('konseling.walkin');
    Route::post('/konseling/walkin', [KonselingController::class, 'walkinStore'])->name('konseling.walkin.store');
    Route::get('/konseling/{id}', [KonselingController::class, 'show'])->name('konseling.show');
    Route::post('/konseling/{id}/konfirmasi', [KonselingController::class, 'konfirmasi'])->name('konseling.konfirmasi');
    Route::post('/konseling/{id}/laporan', [KonselingController::class, 'laporan'])->name('konseling.laporan');
    Route::post('/konseling/{id}/batal', [KonselingController::class, 'batalGuru'])->name('konseling.batal');
    Route::get('/chat/{konselingId}', [\App\Http\Controllers\Web\ChatController::class, 'roomGuru'])->name('chat');
    Route::get('/notifikasi/json', [NotifikasiWebController::class, 'jsonGuru'])->name('notifikasi.json');
    Route::post('/notifikasi/read-all', [NotifikasiWebController::class, 'readAll'])->name('notifikasi.readAll');
    Route::get('/notifikasi/{id}/read', [NotifikasiWebController::class, 'markReadGuru'])->name('notifikasi.read');
    Route::post('/chat/{konselingId}', [\App\Http\Controllers\Web\ChatController::class, 'sendGuru'])->name('chat.send');
    Route::get('/chat/{konselingId}/messages', [\App\Http\Controllers\Web\ChatController::class, 'historyJson'])->name('chat.messages');
    Route::get('/siswa', [SiswaController::class, 'index'])->name('siswa.index');
    Route::get('/siswa/tambah', [SiswaController::class, 'create'])->name('siswa.create');
    Route::get('/siswa/template', [SiswaController::class, 'template'])->name('siswa.template');
    Route::get('/siswa/lookup/{nis}', [SiswaController::class, 'lookupByNis'])->name('siswa.lookup');
    Route::post('/siswa/import', [SiswaController::class, 'importExcel'])->name('siswa.import');
    Route::post('/siswa/import-absen/preview', [SiswaController::class, 'previewAbsen'])->name('siswa.importAbsen.preview');
    Route::post('/siswa/import-rows', [SiswaController::class, 'importRows'])->name('siswa.importRows');
    Route::post('/siswa', [SiswaController::class, 'store'])->name('siswa.store');
    Route::get('/siswa/{id}/edit', [SiswaController::class, 'edit'])->name('siswa.edit');
    Route::put('/siswa/{id}', [SiswaController::class, 'update'])->name('siswa.update');
    Route::get('/informasi', [InformasiController::class, 'index'])->name('informasi');
    Route::get('/informasi/tambah', [InformasiController::class, 'create'])->name('informasi.create');
    Route::post('/informasi', [InformasiController::class, 'store'])->name('informasi.store');
    Route::get('/informasi/{id}/edit', [InformasiController::class, 'edit'])->name('informasi.edit');
    Route::put('/informasi/{id}', [InformasiController::class, 'update'])->name('informasi.update');
    Route::delete('/informasi/{id}', [InformasiController::class, 'destroy'])->name('informasi.destroy');
    Route::get('/cetak-laporan', [\App\Http\Controllers\Web\CetakLaporanController::class, '__invoke'])->name('cetak');
    Route::get('/jadwal-rutin', [\App\Http\Controllers\Web\JadwalRutinController::class, 'index'])->name('jadwal-rutin.index');
    Route::post('/jadwal-rutin', [\App\Http\Controllers\Web\JadwalRutinController::class, 'store'])->name('jadwal-rutin.store');
    Route::post('/jadwal-rutin/{id}/toggle', [\App\Http\Controllers\Web\JadwalRutinController::class, 'toggle'])->name('jadwal-rutin.toggle');
    Route::delete('/jadwal-rutin/{id}', [\App\Http\Controllers\Web\JadwalRutinController::class, 'destroy'])->name('jadwal-rutin.destroy');
});

Route::middleware('role:kepsek')->prefix('kepsek')->name('kepsek.')->group(function () {
    Route::get('/dashboard', [KepsekController::class, 'dashboard'])->name('dashboard');
    Route::get('/rekap-guru', [KepsekController::class, 'rekapGuru'])->name('rekap');
    Route::get('/konseling', [KepsekController::class, 'konseling'])->name('konseling');
    Route::get('/konseling/{id}', [KepsekController::class, 'show'])->name('konseling.show');
    Route::get('/statistik', [KepsekController::class, 'statistik'])->name('statistik');
    Route::get('/informasi', [InformasiController::class, 'index'])->name('informasi');
});

Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/guru', [AkunController::class, 'guruIndex'])->name('guru.index');
    Route::get('/guru/tambah', [AkunController::class, 'guruCreate'])->name('guru.create');
    Route::post('/guru', [AkunController::class, 'guruStore'])->name('guru.store');
    Route::get('/guru/{id}/edit', [AkunController::class, 'guruEdit'])->name('guru.edit');
    Route::put('/guru/{id}', [AkunController::class, 'guruUpdate'])->name('guru.update');
    Route::get('/kepsek', [AkunController::class, 'kepsekIndex'])->name('kepsek.index');
    Route::get('/kepsek/tambah', [AkunController::class, 'kepsekCreate'])->name('kepsek.create');
    Route::post('/kepsek', [AkunController::class, 'kepsekStore'])->name('kepsek.store');
    Route::get('/kepsek/{id}/edit', [AkunController::class, 'kepsekEdit'])->name('kepsek.edit');
    Route::put('/kepsek/{id}', [AkunController::class, 'kepsekUpdate'])->name('kepsek.update');
    Route::post('/guru/{id}/deactivate', [AkunController::class, 'guruDeactivate'])->name('guru.deactivate');
Route::post('/guru/{id}/activate', [AkunController::class, 'guruActivate'])->name('guru.activate');
Route::post('/kepsek/{id}/deactivate', [AkunController::class, 'kepsekDeactivate'])->name('kepsek.deactivate');
Route::post('/kepsek/{id}/activate', [AkunController::class, 'kepsekActivate'])->name('kepsek.activate');
});

Route::middleware('role:siswa,guru,kepsek,admin')->get('/informasi', [InformasiController::class, 'index'])->name('informasi.index');
