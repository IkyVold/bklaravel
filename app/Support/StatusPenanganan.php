<?php

namespace App\Support;

/**
 * Master status penanganan laporan konseling — satu-satunya sumber
 * daftar nilai yang sah, meniru pola KategoriKonseling.
 *
 * PERBAIKAN (revisi 26 Agustus 2026, poin 6): laporan_status_penanganan
 * sebelumnya divalidasi sebagai 'string|max:80' saja, baik di Web
 * maupun API. KonselingReportService hanya mengaktifkan kewajiban
 * sesi lanjutan kalau nilainya PERSIS 'Monitoring' (perbandingan
 * string ketat) — request manual bisa mengirim 'monitoring',
 * 'Monitoring ' (ada spasi), atau nilai lain sama sekali dan tetap
 * lolos menyelesaikan laporan tanpa aturan follow-up. Sekarang seluruh
 * jalur (Web, API, dan KonselingReportService sebagai lapisan
 * pertahanan terakhir) memvalidasi terhadap StatusPenanganan::ALL.
 *
 * Nilai & urutan array ini SENGAJA disamakan persis dengan
 * $statusPenangananOptions yang sudah dipakai
 * resources/views/guru/konseling-detail.blade.php — blade tersebut
 * sekarang mengambil labelnya dari StatusPenanganan::LABELS supaya
 * dropdown dan validasi tidak bisa lagi diam-diam berbeda.
 */
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
