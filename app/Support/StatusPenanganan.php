<?php

namespace App\Support;

final class StatusPenanganan
{
    public const SELESAI_TERATASI = 'Selesai - Masalah Teratasi';
    public const MONITORING = 'Monitoring';
    public const RUJUK = 'Rujuk';
    public const RUJUK_BK_LAIN = 'Rujuk BK Lain';
    public const ORANG_TUA = 'Orang Tua';

    public const ALL = [
        self::SELESAI_TERATASI,
        self::MONITORING,
        self::RUJUK,
        self::RUJUK_BK_LAIN,
        self::ORANG_TUA,
    ];

    /** Label tampilan (dipakai dropdown form laporan Guru BK). */
    public const LABELS = [
        self::SELESAI_TERATASI => '✅ Selesai - Masalah Teratasi',
        self::MONITORING => '📊 Perlu Monitoring Lanjutan',
        self::RUJUK => '🔄 Dirujuk ke pihak lain (Guru Mapel/Wali Kelas)',
        self::RUJUK_BK_LAIN => '👨‍🏫 Dirujuk ke Guru BK Lain',
        self::ORANG_TUA => '👨‍👩‍👧 Perlu keterlibatan Orang Tua',
    ];
}
