<?php

namespace App\Support;

/**
 * Generator password awal (sementara) untuk akun siswa baru.
 *
 * PERBAIKAN (revisi 27 Agustus 2026, poin 1): sebelumnya password awal
 * siswa baru SELALU = NIS mereka sendiri (lihat riwayat di
 * Web\SiswaController::store()/upsertSiswa() dan Api\SiswaController::
 * create()/importRows()). NIS BUKAN rahasia — biasanya tertera di kartu
 * pelajar, absensi, atau rapor — sehingga siapa pun yang tahu NIS
 * seorang siswa otomatis tahu password awalnya tanpa perlu menebak apa
 * pun. Mekanisme must_change_password memang memaksa siswa mengganti
 * password SETELAH berhasil login, tapi tidak mencegah pihak lain LOGIN
 * LEBIH DULU memakai NIS sebagai password sebelum siswa yang asli
 * sempat login pertama kali.
 *
 * Sekarang password awal digantikan string acak yang sama sekali tidak
 * berhubungan dengan NIS. Konsekuensinya: string ini WAJIB ditampilkan
 * ke Guru BK/Admin yang membuat akun (lewat response/flash message)
 * supaya bisa disampaikan secara manual ke siswa — pemanggil helper ini
 * bertanggung jawab menampilkannya, karena begitu request selesai,
 * password ini tidak disimpan di mana pun dalam bentuk plain text.
 */
final class TempPassword
{
    /**
     * Panjang 10 karakter dipilih supaya otomatis memenuhi aturan
     * panjang minimum yang sudah dipakai untuk password custom Admin di
     * Api\SiswaController ("password kustom harus minimal 10 karakter"),
     * jadi hanya ada SATU standar panjang password minimum di sistem.
     */
    private const LENGTH = 10;

    /**
     * Karakter yang dipakai SENGAJA menghindari karakter yang gampang
     * salah baca/salah ketik saat disalin manusia dari layar/kertas ke
     * form login: 0/O dan 1/I/l dihilangkan dari alfabet.
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Menghasilkan satu password acak baru. Memakai random_int() (bukan
     * rand()/mt_rand()) supaya hasilnya cryptographically secure — sama
     * seperti alasan Str::random() dipilih di kode lain yang berurusan
     * dengan token/identitas rahasia.
     */
    public static function generate(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $password = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }
        return $password;
    }
}
