<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 27 Agustus 2026, poin 2): reset password siswa oleh
 * Admin (lihat Api\ProfileController@update) sebelumnya HANYA mengubah
 * kolom password — tidak ada mekanisme apa pun untuk memutus token/
 * session siswa yang sudah terlanjur aktif sebelum reset terjadi. Kalau
 * akun siswa sudah dibajak (attacker sudah punya token/session), reset
 * password oleh Admin sama sekali tidak menutup akses attacker tsb.
 *
 * Guru BK/Kepsek/Admin sudah punya mekanisme ini sejak migration
 * add_password_changed_at_to_staff (revisi 26 Agustus 2026, poin 3) —
 * migration ini menambahkan kolom yang SAMA untuk tabel siswa supaya
 * siswa bisa memakai pola perlindungan yang identik:
 *
 * password_changed_at: stempel waktu untuk keperluan audit/informasi.
 *
 * password_version: angka yang SELALU naik setiap kali password
 * benar-benar diganti (lihat Siswa::setPasswordAttribute()). Dipakai
 * RoleAuth (middleware Web) untuk membandingkan baseline session siswa
 * ke database pada setiap request — persis seperti perlakuan Guru BK/
 * Kepsek/Admin. Sengaja dipakai counter, BUKAN semata-mata
 * membandingkan password_changed_at, karena timestamp hanya presisi
 * detik dan berisiko tabrakan kalau login & reset password terjadi
 * dalam detik yang sama.
 *
 * Jalur API tetap memakai pencabutan token Sanctum secara eksplisit
 * ($siswa->tokens()->delete()) di titik reset — bukan lewat perbandingan
 * password_version — persis pola yang sudah dipakai
 * Api\AkunController@updateGuru/@updateKepsek untuk staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            if (!Schema::hasColumn('siswa', 'password_version')) {
                $table->unsignedInteger('password_version')->default(1)->after('password_changed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'password_version')) {
                $table->dropColumn('password_version');
            }
            if (Schema::hasColumn('siswa', 'password_changed_at')) {
                $table->dropColumn('password_changed_at');
            }
        });
    }
};
