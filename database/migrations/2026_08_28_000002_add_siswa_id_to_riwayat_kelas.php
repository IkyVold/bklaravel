<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 27 Agustus 2026, poin 3): riwayat_kelas.nis
 * sebelumnya varchar(20) (dari migration awal create_bk_tables),
 * sementara siswa.nis sudah dipersempit jadi varchar(4) sejak migration
 * shorten_nis_to_4_digits — dua definisi lebar berbeda untuk hal yang
 * sama di lapisan database. Selain itu, relasi riwayat_kelas ke siswa
 * dilakukan lewat NIS sebagai STRING, bukan lewat foreign key ke baris
 * siswa yang sesungguhnya. Kalau suatu hari NIS seorang siswa berubah
 * (mis. karena kesalahan input yang diperbaiki, atau kebijakan sekolah
 * berubah), seluruh riwayat kelas lamanya langsung terputus dari siswa
 * tsb karena tidak ada lagi baris siswa dengan NIS itu.
 *
 * Migration ini mengganti dasar relasinya jadi siswa_id (foreign key
 * sesungguhnya ke siswa.id) — cara yang sama seperti konseling.siswa_id
 * — supaya riwayat kelas tetap terhubung ke siswa yang sama walau NIS-
 * nya berubah nanti. Kolom 'nis' pada riwayat_kelas SENGAJA dihapus
 * sepenuhnya (bukan sekadar dipersempit jadi varchar(4)) karena setelah
 * ada siswa_id, kolom itu jadi data turunan yang bisa basi/tidak sinkron
 * kalau NIS induknya berubah — satu-satunya sumber kebenaran untuk NIS
 * tetap siswa.nis (diakses lewat relasi siswa()). Kontrak API publik
 * (Api\RiwayatKelasController, endpoint /api/riwayat-kelas/{nis}) TIDAK
 * berubah — nis tetap dipakai sebagai parameter URL/identitas dari
 * sisi klien, hanya representasi internalnya yang berubah jadi FK.
 *
 * Backfill dilakukan per-baris (bukan JOIN UPDATE) supaya jalan sama
 * persis di MySQL (production) maupun SQLite (dipakai test suite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('riwayat_kelas', 'siswa_id')) {
            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->unsignedBigInteger('siswa_id')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('riwayat_kelas', 'nis')) {
            DB::table('riwayat_kelas')->whereNull('siswa_id')->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $siswaId = DB::table('siswa')->where('nis', $row->nis)->value('id');
                        if ($siswaId) {
                            DB::table('riwayat_kelas')->where('id', $row->id)->update(['siswa_id' => $siswaId]);
                        }
                    }
                });

            // PERBAIKAN (mengikuti pola preflight check yang sama dengan
            // migration add_unique_pengajuan_sebelumnya_id): kolom 'nis'
            // di bawah adalah SATU-SATUNYA jejak yang menghubungkan baris
            // riwayat_kelas lama ke siswa pemiliknya. Sebelum kolom itu
            // dihapus, WAJIB dipastikan setiap baris sudah berhasil
            // mendapat siswa_id. Kalau ada baris yatim (NIS yang sudah
            // tidak cocok dengan siswa manapun — mis. siswa sudah tidak
            // ada), migration BERHENTI dengan pesan jelas alih-alih diam-
            // diam membuang jejak baris tsb ke siswa yang mana pun.
            $orphanCount = DB::table('riwayat_kelas')->whereNull('siswa_id')->count();
            if ($orphanCount > 0) {
                throw new RuntimeException(
                    "Migration add_siswa_id_to_riwayat_kelas dihentikan: {$orphanCount} baris riwayat_kelas ".
                    "punya NIS yang tidak cocok dengan siswa manapun. Perbaiki atau hapus baris tsb secara ".
                    "eksplisit (mis. lewat query manual) sebelum migration ini dijalankan ulang, supaya ".
                    "riwayat kelas tidak kehilangan jejak siswa pemiliknya saat kolom 'nis' dihapus."
                );
            }
        }

        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->unsignedBigInteger('siswa_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('riwayat_kelas', 'nis')) {
            // Index unique lama berbasis nis (dari migration awal
            // create_bk_tables, bernama 'unique_nis_tahun') dihapus dulu
            // secara eksplisit sebelum kolom 'nis' yang menjadi bagian
            // dari index itu dihapus — index tidak bisa dihapus kolomnya
            // begitu saja selama masih dipakai sebuah index.
            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->dropUnique('unique_nis_tahun');
            });

            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->dropColumn('nis');
            });
        }

        $indexes = collect(Schema::getIndexes('riwayat_kelas'))->pluck('name');
        if (!$indexes->contains('unique_siswa_tahun')) {
            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->unique(['siswa_id', 'tahun_ajaran'], 'unique_siswa_tahun');
            });
        }
        if (!$indexes->contains('riwayat_kelas_siswa_id_index')) {
            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->index('siswa_id', 'riwayat_kelas_siswa_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('riwayat_kelas', function (Blueprint $table) {
            if (Schema::hasColumn('riwayat_kelas', 'siswa_id')) {
                $table->dropUnique('unique_siswa_tahun');
                $table->dropIndex('riwayat_kelas_siswa_id_index');
            }
        });

        if (!Schema::hasColumn('riwayat_kelas', 'nis')) {
            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->string('nis', 20)->nullable()->after('id');
            });

            DB::table('riwayat_kelas')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $nis = DB::table('siswa')->where('id', $row->siswa_id)->value('nis');
                    if ($nis) {
                        DB::table('riwayat_kelas')->where('id', $row->id)->update(['nis' => $nis]);
                    }
                }
            });

            Schema::table('riwayat_kelas', function (Blueprint $table) {
                $table->string('nis', 20)->nullable(false)->change();
                $table->unique(['nis', 'tahun_ajaran'], 'unique_nis_tahun');
            });
        }

        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->dropColumn('siswa_id');
        });
    }
};
