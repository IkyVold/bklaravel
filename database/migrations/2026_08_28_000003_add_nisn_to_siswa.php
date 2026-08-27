<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LATAR BELAKANG (ditemukan saat menjalankan migration
 * shorten_nis_to_4_digits di database production): sebagian siswa
 * (khususnya angkatan kelas X yang baru) ternyata memiliki nilai di
 * kolom `nis` yang sebenarnya adalah NISN nasional 10 digit, bukan NIS
 * lokal sekolah 4 digit — kemungkinan salah input saat pendaftaran siswa
 * baru (field NIS lokal tertukar dengan NISN).
 *
 * Sebelum nilai 10 digit itu ditimpa dengan NIS lokal baru (lihat
 * Console\Commands\FixInvalidNisLength), nilai aslinya diarsipkan dulu
 * ke kolom `nisn` supaya tidak hilang — NISN tetap berguna untuk
 * keperluan administratif lain (mis. pelaporan ke Dapodik) meskipun
 * bukan identitas login di sistem ini.
 *
 * Kolom ini SENGAJA dibuat nullable dan tidak unique: siswa lama yang
 * NIS-nya sudah benar dari awal tidak perlu mengisi kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'nisn')) {
                $table->string('nisn', 20)->nullable()->after('nis');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'nisn')) {
                $table->dropColumn('nisn');
            }
        });
    }
};