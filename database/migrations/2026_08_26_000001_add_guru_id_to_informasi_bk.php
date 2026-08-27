<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 26 Agustus 2026, poin 4): informasi_bk sebelumnya
 * hanya menyimpan `guru_bk` (nama, string bebas) tanpa `guru_id`. Karena
 * itu, InformasiController::update()/remove() tidak punya cara yang
 * aman untuk memastikan seorang Guru BK hanya mengubah/menghapus
 * informasi miliknya sendiri — nama BUKAN identifier unik (dua Guru BK
 * bisa bernama sama), dan mengandalkannya akan mengulang bug yang sama
 * dengan yang sudah diperbaiki di Konseling (lihat guruOwnsKonseling()
 * di AuthorizesBk). Kolom ini melengkapi itu; guru_bk (nama) TETAP
 * disimpan untuk tampilan/attribution histori, tapi guru_id sekarang
 * jadi satu-satunya sumber kebenaran untuk ownership.
 *
 * Backfill: baris lama dicocokkan ke guru_bk (tabel) lewat nama secara
 * best-effort. Baris yang tidak match (nama tidak ditemukan, atau ada
 * lebih dari satu Guru BK dengan nama sama) dibiarkan guru_id NULL —
 * dianggap data lama tanpa pemilik pasti, sama seperti pola konseling
 * lama yang guru_id-nya null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informasi_bk', function (Blueprint $table) {
            if (!Schema::hasColumn('informasi_bk', 'guru_id')) {
                $table->unsignedBigInteger('guru_id')->nullable()->after('guru_bk')->index();
            }
        });

        if (Schema::hasTable('guru_bk')) {
            $duplikatNama = DB::table('guru_bk')
                ->select('nama')
                ->groupBy('nama')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('nama');

            DB::table('informasi_bk')
                ->whereNull('guru_id')
                ->whereNotIn('guru_bk', $duplikatNama)
                ->orderBy('id')
                ->each(function ($row) {
                    $guru = DB::table('guru_bk')->where('nama', $row->guru_bk)->first();
                    if ($guru) {
                        DB::table('informasi_bk')->where('id', $row->id)->update(['guru_id' => $guru->id]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('informasi_bk', function (Blueprint $table) {
            if (Schema::hasColumn('informasi_bk', 'guru_id')) {
                $table->dropColumn('guru_id');
            }
        });
    }
};
