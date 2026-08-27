<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (revisi 26 Agustus 2026, poin 8): sebelum ini sistem punya
 * TIGA definisi panjang NIS yang berbeda-beda dan saling bertentangan:
 *   - migration (kolom database) → varchar(10)
 *   - Api\SiswaController        → validasi max:10
 *   - Web\SiswaController        → validasi max:20
 *   - halaman login              → teks bilang "NIS harus 4 digit angka"
 *     tapi atribut maxlength pada input malah 10
 *
 * Setelah didiskusikan, diputuskan NIS di sistem ini adalah NIS LOKAL
 * SEKOLAH, bukan NISN nasional (yang selalu 10 digit) — jadi standar
 * yang dipakai sekarang adalah TEPAT 4 digit angka, sesuai yang sudah
 * dijanjikan ke pengguna di halaman login. Aturan ini diseragamkan ke
 * migration (kolom), API, Web (create/edit/import), dan login.
 *
 * Kolom dipersempit dari varchar(10) menjadi varchar(4) supaya lapisan
 * database ikut menegakkan batas ini, bukan cuma mengandalkan validasi
 * di controller (yang bisa saja lupa ditambahkan di endpoint baru).
 *
 * PERBAIKAN (bug ditemukan saat migrate di database production yang
 * sudah lama berjalan): versi sebelumnya di sini LANGSUNG memanggil
 * dropUnique('siswa_nis_unique') dengan asumsi index itu SELALU ada
 * dengan nama tsb, karena kolom 'nis' di create_bk_tables.php sekarang
 * memang punya ->unique(). Tapi kalau migration create_bk_tables itu
 * sudah tercatat "sudah pernah dijalankan" di tabel `migrations` sejak
 * SEBELUM ->unique() ditambahkan ke file tsb (Laravel tidak menjalankan
 * ulang migration yang sudah tercatat walau isi filenya berubah), kolom
 * 'nis' bisa saja TIDAK punya unique index sama sekali di database yang
 * bersangkutan — dropUnique() lalu gagal dengan error SQL "Can't DROP
 * INDEX `siswa_nis_unique`; check that it exists". Pola bug yang persis
 * sama dengan yang sudah diperbaiki di migration
 * add_unique_pengajuan_sebelumnya_id, tetapi belum sempat diterapkan di
 * sini.
 *
 * Sekarang index unique pada kolom 'nis' (nama apa pun) hanya di-drop
 * KALAU benar-benar ditemukan lewat Schema::getIndexes(), dan index baru
 * hanya dibuat kalau belum ada index unique bernama 'siswa_nis_unique' —
 * konsisten dengan pola defensif yang sama di migration lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropExistingUniqueOnNis();

        Schema::table('siswa', function ($table) {
            $table->string('nis', 4)->change();
        });

        $uniqueSudahAda = collect(Schema::getIndexes('siswa'))
            ->contains('name', 'siswa_nis_unique');

        if (!$uniqueSudahAda) {
            Schema::table('siswa', function ($table) {
                $table->unique('nis', 'siswa_nis_unique');
            });
        }
    }

    public function down(): void
    {
        $this->dropExistingUniqueOnNis();

        Schema::table('siswa', function ($table) {
            $table->string('nis', 10)->change();
        });

        $uniqueSudahAda = collect(Schema::getIndexes('siswa'))
            ->contains('name', 'siswa_nis_unique');

        if (!$uniqueSudahAda) {
            Schema::table('siswa', function ($table) {
                $table->unique('nis', 'siswa_nis_unique');
            });
        }
    }

    /**
     * Hapus index unique mana pun yang menaungi kolom 'nis' sendirian
     * (bukan bagian dari composite index), apa pun namanya di database
     * ini. Dicek berdasarkan definisi index (kolom penyusun + flag
     * unique), bukan hanya menebak satu nama tetap.
     */
    private function dropExistingUniqueOnNis(): void
    {
        $index = collect(Schema::getIndexes('siswa'))
            ->first(fn ($idx) => $idx['unique'] && $idx['columns'] === ['nis']);

        if ($index) {
            Schema::table('siswa', function ($table) use ($index) {
                $table->dropUnique($index['name']);
            });
        }
    }
};