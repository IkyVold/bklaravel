<?php

namespace App\Support;

/**
 * Master kategori konseling — satu-satunya sumber daftar kategori yang
 * sah. Sebelumnya validator hanya memakai 'string|max:50' sehingga
 * request bisa mengirim kategori apa pun dan berpotensi merusak
 * statistik Kepsek. Sekarang seluruh validasi (Web & API) memakai
 * Rule::in(KategoriKonseling::ALL), dan rekap statistik memakai daftar
 * yang sama supaya tidak ada kategori yang diam-diam tertinggal.
 *
 * Urutan array ini juga menentukan urutan tampil di form pilih kategori
 * dan di rekap.
 */
final class KategoriKonseling
{
    public const AKADEMIK = 'Akademik';
    public const SOSIAL = 'Sosial';
    public const PRIBADI = 'Pribadi';
    public const KARIR = 'Karir';
    public const BULLYING = 'Bullying';
    public const KELUARGA = 'Keluarga';

    public const ALL = [
        self::AKADEMIK,
        self::SOSIAL,
        self::PRIBADI,
        self::KARIR,
        self::BULLYING,
        self::KELUARGA,
    ];
}
