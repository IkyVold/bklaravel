<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 24 Agustus 2026, poin 11): ScheduleService::hasConflict()
 * sebelumnya hanya mencocokkan tanggal+jam MULAI yang persis sama. Kalau
 * durasi sesi konseling 60 menit, dua sesi jam 10.00 dan 10.30 tidak
 * dianggap bentrok padahal jelas overlap.
 *
 * 'durasi_menit' nullable — sesi lama/yang tidak mengisi durasi dianggap
 * memakai ScheduleService::DEFAULT_DURATION_MINUTES (60 menit) saat
 * dicek overlap, sama seperti pola yang sudah dipakai JadwalRutinController
 * untuk slot tanpa jam_selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (!Schema::hasColumn('konseling', 'durasi_menit')) {
                $table->unsignedSmallInteger('durasi_menit')->nullable()->after('jam');
            }
        });
    }

    public function down(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            $table->dropColumn('durasi_menit');
        });
    }
};
