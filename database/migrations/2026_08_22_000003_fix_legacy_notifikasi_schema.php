<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Beberapa instalasi lama sudah punya tabel `notifikasi` dengan skema lama
 * (siswa_id, konseling_id, is_read — dari versi Node/dump lama) yang dibuat
 * SEBELUM migration 2026_01_01_000001_create_bk_tables.php ada. Karena
 * migration tersebut memakai `if (!Schema::hasTable('notifikasi'))`, tabel
 * lama itu tidak pernah diganti strukturnya — akibatnya model Notifikasi
 * (yang sudah dipakai konsisten oleh web & API dengan skema baru) gagal
 * dengan error "Unknown column 'penerima_id'".
 *
 * Migration ini menambahkan kolom skema baru pada tabel lama TANPA
 * menghapus data yang sudah ada, lalu memindahkan data dari kolom lama ke
 * kolom baru bila kolom lama tersebut ada. Aman dijalankan berulang kali
 * (idempotent) dan aman pada instalasi baru yang memang sudah memakai
 * skema baru sejak awal (semua langkah dibungkus pengecekan hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifikasi')) {
            return;
        }

        $hasNewSchema = Schema::hasColumn('notifikasi', 'penerima_id');

        Schema::table('notifikasi', function (Blueprint $table) {
            if (!Schema::hasColumn('notifikasi', 'penerima_id')) {
                $table->string('penerima_id', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('notifikasi', 'penerima_role')) {
                $table->string('penerima_role', 20)->nullable()->after('penerima_id');
            }
            if (!Schema::hasColumn('notifikasi', 'judul')) {
                $table->string('judul', 150)->nullable();
            }
            if (!Schema::hasColumn('notifikasi', 'pesan')) {
                $table->text('pesan')->nullable();
            }
            if (!Schema::hasColumn('notifikasi', 'tipe')) {
                $table->string('tipe', 50)->nullable();
            }
            if (!Schema::hasColumn('notifikasi', 'data')) {
                $table->json('data')->nullable();
            }
            if (!Schema::hasColumn('notifikasi', 'dibaca')) {
                $table->boolean('dibaca')->default(false);
            }
            if (!Schema::hasColumn('notifikasi', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });

        // Kalau tabel ini sebelumnya sudah pakai skema baru sejak awal,
        // tidak ada apa pun untuk dipindahkan.
        if ($hasNewSchema) {
            return;
        }

        // Pindahkan data lama (siswa_id / konseling_id / is_read) ke kolom
        // baru — best effort, hanya jalan kalau kolom lamanya memang ada.
        $hasSiswaId = Schema::hasColumn('notifikasi', 'siswa_id');
        $hasKonselingId = Schema::hasColumn('notifikasi', 'konseling_id');
        $hasIsRead = Schema::hasColumn('notifikasi', 'is_read');

        if ($hasSiswaId) {
            DB::table('notifikasi')->whereNull('penerima_id')->update([
                'penerima_id' => DB::raw('siswa_id'),
                'penerima_role' => 'siswa',
            ]);
        }

        if ($hasIsRead) {
            DB::table('notifikasi')->update([
                'dibaca' => DB::raw('is_read'),
            ]);
        }

        if ($hasKonselingId) {
            // konseling_id lama dipindah ke kolom json `data`, sesuai skema
            // baru (lihat Notifikasi::getKonselingIdAttribute()).
            DB::table('notifikasi')
                ->whereNotNull('konseling_id')
                ->whereNull('data')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('notifikasi')->where('id', $row->id)->update([
                            'data' => json_encode(['konseling_id' => (int) $row->konseling_id]),
                        ]);
                    }
                });
        }

        // penerima_id/penerima_role/dibaca sekarang jadi kolom wajib bagi
        // aplikasi — pastikan tidak ada baris yang masih NULL karena tidak
        // punya siswa_id lama (mis. notifikasi lama untuk staff).
        DB::table('notifikasi')->whereNull('penerima_role')->update(['penerima_role' => 'siswa']);
        DB::table('notifikasi')->whereNull('dibaca')->update(['dibaca' => false]);
    }

    public function down(): void
    {
        // Aman: tidak menghapus kolom/data agar tidak kehilangan histori
        // notifikasi.
    }
};
