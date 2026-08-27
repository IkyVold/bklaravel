<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 25 Agustus 2026, poin 11): sebelumnya tidak ada
 * mekanisme apa pun yang memaksa siswa mengganti password default.
 * Password awal siswa SELALU di-set = NIS sendiri (lihat
 * Api/SiswaController@create/@importRows dan Web/SiswaController@store/
 * @upsertSiswa) — dan NIS bukan rahasia: sering tertera di kartu
 * pelajar, absensi, rapor, dsb. Selama siswa tidak pernah mengganti
 * password-nya, akun tetap bisa diakses siapa pun yang tahu NIS-nya.
 *
 * Kolom must_change_password menandai akun siswa yang wajib mengganti
 * password sebelum boleh mengakses fitur lain (lihat
 * App\Http\Middleware\EnsurePasswordChanged untuk API dan
 * App\Http\Middleware\RoleAuth untuk web).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        // Tandai retroaktif HANYA akun yang password-nya saat ini masih
        // persis sama dengan NIS mereka sendiri (masih pakai default,
        // belum pernah diganti). Akun yang sudah pernah ganti password
        // tidak disentuh sama sekali, supaya migrasi ini tidak memaksa
        // logout/ganti password massal untuk siswa yang sudah aman.
        DB::table('siswa')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $nis = (string) $row->nis;
                $hash = (string) $row->password;

                $isDefault = str_starts_with($hash, '$2y$')
                    ? password_verify($nis, $hash)
                    : $hash === md5($nis);

                if ($isDefault) {
                    DB::table('siswa')->where('id', $row->id)->update(['must_change_password' => true]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};
