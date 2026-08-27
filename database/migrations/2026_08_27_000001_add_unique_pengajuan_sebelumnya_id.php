<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
 *
 * PERBAIKAN (revisi 27 Agustus 2026, poin 4 — hasil review dosen
 * penguji): versi sebelumnya migration ini LANGSUNG memasang unique
 * index tanpa preflight check. Race condition yang disebutkan di atas
 * memang sudah ada SEBELUM lockForUpdate ditambahkan, sehingga database
 * production yang sudah lama berjalan bisa saja SUDAH memiliki baris
 * duplikat (mis. parent 100 → child 101 dan parent 100 → child 102).
 * Kalau itu terjadi, `$table->unique(...)` akan gagal di tengah jalan
 * dengan error SQL mentah yang tidak menjelaskan baris mana yang
 * bermasalah maupun cara memperbaikinya.
 *
 * Sekarang, sebelum index dipasang, migration menjalankan preflight
 * query (GROUP BY ... HAVING COUNT(*) > 1) untuk mendeteksi duplikasi.
 * Kalau ditemukan, migration BERHENTI dengan RuntimeException yang
 * menyebutkan persis nilai pengajuan_sebelumnya_id mana saja yang
 * duplikat, supaya data bisa diperbaiki secara eksplisit (mis. dengan
 * meninjau baris mana yang seharusnya sesi lanjutan "asli") sebelum
 * migration dijalankan ulang. Pola ini identik dengan orphan-check pada
 * migration add_siswa_id_to_riwayat_kelas.
 *
 * PERBAIKAN (bug ditemukan saat migrate di database yang sudah lama
 * berjalan): versi sebelumnya LANGSUNG memanggil
 * dropIndex('konseling_pengajuan_sebelumnya_id_index') tanpa mengecek
 * dulu apakah index itu benar-benar ada. Asumsinya index itu SELALU ada
 * karena dibuat migration 2026_08_22_000002 lewat ->index(). Tapi kalau
 * migration itu sudah tercatat "sudah pernah dijalankan" di tabel
 * `migrations` sejak SEBELUM ->index() ditambahkan ke file tsb (Laravel
 * tidak menjalankan ulang migration yang sudah tercatat walau isi
 * filenya berubah), kolom pengajuan_sebelumnya_id bisa saja sudah ada
 * di database TANPA index itu — dropIndex() lalu gagal dengan error SQL
 * "Can't DROP INDEX ...; check that it exists" karena memang tidak ada.
 *
 * Sekarang index lama hanya dihapus KALAU benar-benar ditemukan di
 * Schema::getIndexes(), dicek berdasarkan NAMA index (bukan hanya nama
 * kolom) supaya tidak keliru menghapus index lain yang kebetulan juga
 * memuat kolom ini. Kalau index lama sudah tidak ada, langkah ini
 * dilewati saja — tidak mengubah hasil akhir (kolom tetap berakhir
 * hanya punya unique index, bukan index lama + unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            return;
        }

        $duplicates = DB::table('konseling')
            ->select('pengajuan_sebelumnya_id', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('pengajuan_sebelumnya_id')
            ->groupBy('pengajuan_sebelumnya_id')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $daftar = $duplicates
                ->map(fn ($row) => "pengajuan_sebelumnya_id={$row->pengajuan_sebelumnya_id} ({$row->jumlah} baris)")
                ->implode(', ');

            throw new RuntimeException(
                'Migration add_unique_pengajuan_sebelumnya_id dihentikan: ditemukan konseling.pengajuan_sebelumnya_id '.
                "yang duplikat sebelum unique constraint dipasang: {$daftar}. Ini kemungkinan sisa race condition ".
                'versi lama (sebelum lockForUpdate ditambahkan di KonselingReportService) yang sempat membuat lebih '.
                'dari satu sesi lanjutan untuk parent yang sama. Perbaiki data secara eksplisit terlebih dahulu '.
                '(mis. tinjau baris mana yang seharusnya menjadi sesi lanjutan yang sah, lalu kosongkan atau ubah '.
                'pengajuan_sebelumnya_id pada baris duplikat lainnya) sebelum migration ini dijalankan ulang.'
            );
        }

        // Index lama (non-unique) dari migration 2026_08_22_000002 hanya
        // dihapus KALAU benar-benar ada — lihat catatan bug di atas.
        $indexLamaAda = collect(Schema::getIndexes('konseling'))
            ->contains('name', 'konseling_pengajuan_sebelumnya_id_index');

        if ($indexLamaAda) {
            Schema::table('konseling', function (Blueprint $table) {
                $table->dropIndex('konseling_pengajuan_sebelumnya_id_index');
            });
        }

        $uniqueSudahAda = collect(Schema::getIndexes('konseling'))
            ->contains('name', 'konseling_pengajuan_sebelumnya_id_unique');

        if (!$uniqueSudahAda) {
            Schema::table('konseling', function (Blueprint $table) {
                $table->unique('pengajuan_sebelumnya_id', 'konseling_pengajuan_sebelumnya_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            return;
        }

        $uniqueAda = collect(Schema::getIndexes('konseling'))
            ->contains('name', 'konseling_pengajuan_sebelumnya_id_unique');

        if ($uniqueAda) {
            Schema::table('konseling', function (Blueprint $table) {
                $table->dropUnique('konseling_pengajuan_sebelumnya_id_unique');
            });
        }

        $indexLamaAda = collect(Schema::getIndexes('konseling'))
            ->contains('name', 'konseling_pengajuan_sebelumnya_id_index');

        if (!$indexLamaAda) {
            Schema::table('konseling', function (Blueprint $table) {
                $table->index('pengajuan_sebelumnya_id', 'konseling_pengajuan_sebelumnya_id_index');
            });
        }
    }
};
