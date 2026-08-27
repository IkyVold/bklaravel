<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 27 Agustus 2026, poin 6): sebelumnya
 * `pengajuan_sebelumnya_id` hanya diberi ->index() (lihat migration
 * 2026_08_22_000002), bukan ->unique(). Secara bisnis, satu konseling
 * parent hanya boleh mempunyai TEPAT SATU sesi lanjutan langsung
 * (lihat KonselingReportService::simpan() — $hasChildLanjutan dipakai
 * untuk mencegah duplikat). Tanpa unique constraint, aturan itu HANYA
 * ditegakkan di level aplikasi (query "exists()" sebelum insert), yang
 * rentan race condition: dua request laporan yang datang hampir
 * bersamaan untuk parent yang sama bisa keduanya membaca "belum ada
 * child" lalu keduanya membuat sesi lanjutan.
 *
 * Migration ini menambahkan unique index sebagai lapisan pertahanan
 * TERAKHIR di level database — pelengkap, BUKAN pengganti, penguncian
 * baris (lockForUpdate) yang ditambahkan di KonselingReportService pada
 * revisi yang sama. lockForUpdate mencegah race dengan rapi (request
 * kedua menunggu lalu melihat child yang baru dibuat, tidak mencoba
 * insert kedua); unique index ini menjaga integritas data walau suatu
 * saat ada jalur lain (mis. job/queue, query mentah) yang membuat baris
 * konseling tanpa lewat lockForUpdate tsb.
 *
 * NULL tetap boleh berulang pada kolom ini di MySQL/SQLite/Postgres —
 * unique index hanya menolak DUA BARIS DENGAN NILAI SAMA (bukan NULL),
 * jadi konseling yang bukan sesi lanjutan (pengajuan_sebelumnya_id =
 * null) tidak terpengaruh sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            return;
        }

        Schema::table('konseling', function (Blueprint $table) {
            // Index lama (non-unique) dari migration 2026_08_22_000002
            // dihapus dulu supaya tidak ada dua index berbeda di kolom
            // yang sama; nama index default Laravel untuk
            // ->index() adalah "konseling_pengajuan_sebelumnya_id_index".
            $table->dropIndex('konseling_pengajuan_sebelumnya_id_index');
        });

        Schema::table('konseling', function (Blueprint $table) {
            $table->unique('pengajuan_sebelumnya_id', 'konseling_pengajuan_sebelumnya_id_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            return;
        }

        Schema::table('konseling', function (Blueprint $table) {
            $table->dropUnique('konseling_pengajuan_sebelumnya_id_unique');
        });

        Schema::table('konseling', function (Blueprint $table) {
            $table->index('pengajuan_sebelumnya_id', 'konseling_pengajuan_sebelumnya_id_index');
        });
    }
};
