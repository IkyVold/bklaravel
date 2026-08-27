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
 */
return new class extends Migration
{
    public function up(): void
    {
        // PERBAIKAN: kolom 'nis' SUDAH ->unique() sejak migration awal
        // (create_bk_tables.php), index-nya otomatis bernama
        // 'siswa_nis_unique'. Memanggil ->unique()->change() lagi di sini
        // (versi sebelumnya) membuat driver SQLite membangun ulang tabel
        // sambil menyalin index lama itu SEKALIGUS mencoba membuat index
        // unique baru dari deklarasi ->unique() pada call yang sama,
        // sehingga bentrok: "index siswa_nis_unique already exists".
        //
        // Sekarang index lama sengaja di-drop dulu secara eksplisit,
        // BARU kolom diubah panjangnya, baru index unique dibuat ulang
        // dengan nama yang sama — masing-masing langkah di Schema::table()
        // terpisah supaya urutannya pasti dan tidak bergantung pada
        // perilaku implisit ->change() dalam menyalin index di berbagai
        // driver database (SQLite/MySQL/Postgres).
        Schema::table('siswa', function ($table) {
            $table->dropUnique('siswa_nis_unique');
        });

        Schema::table('siswa', function ($table) {
            $table->string('nis', 4)->change();
        });

        Schema::table('siswa', function ($table) {
            $table->unique('nis', 'siswa_nis_unique');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function ($table) {
            $table->dropUnique('siswa_nis_unique');
        });

        Schema::table('siswa', function ($table) {
            $table->string('nis', 10)->change();
        });

        Schema::table('siswa', function ($table) {
            $table->unique('nis', 'siswa_nis_unique');
        });
    }
};
