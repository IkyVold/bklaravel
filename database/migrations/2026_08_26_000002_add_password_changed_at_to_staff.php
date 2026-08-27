<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 26 Agustus 2026, poin 3): reset password Guru BK/
 * Kepsek/Admin sudah mencabut token Sanctum (lihat
 * Api\AkunController@updateGuru/@updateKepsek/@deleteGuru/@deleteKepsek),
 * tetapi session Web/Blade yang sudah terlanjur login tidak ikut
 * terputus. RoleAuth (middleware web) sebelumnya hanya memeriksa apakah
 * akun masih ada dan is_active masih true — tidak pernah memeriksa
 * apakah password berubah SETELAH session dibuat.
 *
 * password_changed_at: stempel waktu untuk keperluan audit/informasi.
 *
 * password_version: angka yang SELALU naik setiap kali password
 * benar-benar diganti — inilah yang dipakai RoleAuth untuk membandingkan
 * baseline session ke database (lihat GuruBk/Kepsek/Admin::
 * setPasswordAttribute()). Sengaja dipakai counter, BUKAN semata-mata
 * membandingkan password_changed_at: timestamp hanya presisi detik
 * (baik di kolom TIMESTAMP MySQL maupun saat diserialisasi via
 * toDateTimeString()), sehingga login dan reset password yang terjadi
 * dalam detik yang sama berisiko menghasilkan nilai yang SAMA dan gagal
 * terdeteksi sebagai perubahan. Counter integer tidak punya risiko
 * tabrakan seperti itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['guru_bk', 'kepsek', 'admin'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'password_changed_at')) {
                    $blueprint->timestamp('password_changed_at')->nullable()->after('password');
                }
                if (!Schema::hasColumn($table, 'password_version')) {
                    $blueprint->unsignedInteger('password_version')->default(1)->after('password_changed_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['guru_bk', 'kepsek', 'admin'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'password_version')) {
                    $blueprint->dropColumn('password_version');
                }
                if (Schema::hasColumn($table, 'password_changed_at')) {
                    $blueprint->dropColumn('password_changed_at');
                }
            });
        }
    }
};
