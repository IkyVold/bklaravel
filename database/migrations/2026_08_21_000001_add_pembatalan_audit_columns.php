<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi audit trail pembatalan konseling: status + alasan_batal saja
 * tidak cukup untuk tahu siapa yang membatalkan dan kapan. Dipakai oleh
 * satu-satunya jalur pembatalan siswa: batalSiswa() (soft cancel),
 * menggantikan hard-delete yang sebelumnya ada di destroySiswa().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (!Schema::hasColumn('konseling', 'dibatalkan_oleh')) {
                $table->string('dibatalkan_oleh', 100)->nullable();
            }
            if (!Schema::hasColumn('konseling', 'waktu_batal')) {
                $table->timestamp('waktu_batal')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan_oleh', 'waktu_batal']);
        });
    }
};
