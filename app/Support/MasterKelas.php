<?php

namespace App\Support;

/**
 * Sumber kebenaran TUNGGAL untuk daftar kelas yang valid di sistem.
 *
 * PERBAIKAN (revisi 27 Agustus 2026, poin 9): sebelumnya daftar kelas valid
 * (dulu bernama VALID_KELAS) hanya didefinisikan sebagai konstanta lokal di
 * Http\Controllers\Web\SiswaController, dan hanya dipakai oleh jalur Web
 * (form tambah/edit siswa + import CSV/manual). Endpoint
 * Http\Controllers\Api\SiswaController (create() dan importRows()) sama
 * sekali tidak memeriksa kelas terhadap daftar ini — rule-nya masih
 * 'required|string|max:20' — sehingga request API bisa membuat siswa dengan
 * kelas bebas (mis. "KELAS SEMBARANG") meskipun Web sudah menolaknya. Ini
 * bisa merusak statistik/filtering berdasarkan kelas.
 *
 * Daftar kelas sekarang dipindahkan ke class bersama ini (bukan lagi milik
 * satu controller tertentu), dan Web\SiswaController maupun
 * Api\SiswaController sama-sama memvalidasi terhadap sumber yang sama ini.
 *
 * Kalau daftar kelas sekolah berubah (mis. tahun ajaran baru menambah/
 * mengurangi rombel), cukup ubah di SATU tempat ini — Web, API, dan import
 * akan otomatis ikut memakai daftar yang baru.
 */
final class MasterKelas
{
    public const LIST = [
        'X - 1', 'X - 2', 'X - 3', 'X - 4', 'X - 5', 'X - 6', 'X - 7', 'X - 8', 'X - 9', 'X - 10',
        'XI - 1', 'XI - 2', 'XI - 3', 'XI - 4', 'XI - 5', 'XI - 6', 'XI - 7', 'XI - 8', 'XI - 9', 'XI - 10',
        'XII - 1', 'XII - 2', 'XII - 3', 'XII - 4', 'XII - 5', 'XII - 6', 'XII - 7', 'XII - 8', 'XII - 9', 'XII - 10',
    ];

    /**
     * Cek apakah sebuah nilai kelas termasuk daftar kelas yang valid.
     * Perbandingan strict (===) dan case-sensitive dengan sengaja, supaya
     * konsisten dengan pengecekan in_array(..., true) yang sudah dipakai
     * di jalur Web sebelumnya.
     */
    public static function isValid(?string $kelas): bool
    {
        return $kelas !== null && in_array($kelas, self::LIST, true);
    }
}
