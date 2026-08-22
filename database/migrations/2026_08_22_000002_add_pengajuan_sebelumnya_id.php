<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model Konseling dan Web\KonselingController sudah lama memakai
 * `pengajuan_sebelumnya_id` untuk menautkan sesi lanjutan ke pengajuan
 * asalnya (fitur "Lanjutan #id"), tetapi kolom ini tidak pernah dibuat
 * lewat migration — kode hanya mengecek Schema::hasColumn() sebagai
 * workaround. Akibatnya fitur sesi lanjutan diam-diam kehilangan relasi
 * pada database baru. Kolom ini melengkapi migration yang hilang
 * tersebut; Schema::hasColumn() di controller sengaja tidak dihapus agar
 * kode tetap aman berjalan pada database lama yang belum migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
                $table->unsignedBigInteger('pengajuan_sebelumnya_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
                $table->dropColumn('pengajuan_sebelumnya_id');
            }
        });
    }
};
