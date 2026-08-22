<?php

namespace App\Services;

use App\Models\Konseling;

/**
 * Satu-satunya sumber aturan bentrok jadwal konseling — dipakai oleh
 * pengajuan web, API, konfirmasi (web & API), walk-in, dan sesi lanjutan.
 * Jangan duplikasi query conflict-check di controller lain; panggil
 * service ini agar aturan bentrok selalu konsisten di semua jalur.
 */
class ScheduleService
{
    /**
     * True jika siswa ATAU guru yang bersangkutan sudah mempunyai konseling
     * aktif (belum Dibatalkan/Ditolak/Selesai) pada tanggal & jam yang sama.
     *
     * @param  int|null  $excludeId  ID konseling yang sedang diedit (dikecualikan dari cek)
     */
    public function hasConflict(
        ?int $siswaId,
        ?int $guruId,
        ?string $guruBk,
        string $tanggal,
        string $jam,
        ?int $excludeId = null
    ): bool {
        $query = Konseling::where('tanggal', $tanggal)
            ->where('jam', $jam)
            ->whereNotIn('status', ['Dibatalkan', 'Ditolak', 'Selesai'])
            ->where(function ($q) use ($siswaId, $guruId, $guruBk) {
                $q->where(function ($qq) use ($siswaId) {
                    if ($siswaId) {
                        $qq->orWhere('siswa_id', $siswaId);
                    }
                });
                if ($guruId) {
                    $q->orWhere('guru_id', $guruId);
                }
                if ($guruBk) {
                    $q->orWhere('guru_bk', $guruBk);
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Sama seperti hasConflict(), tapi langsung menerima instance Konseling
     * (dipakai saat konfirmasi/reschedule — otomatis exclude dirinya sendiri).
     */
    public function hasConflictFor(Konseling $row, string $tanggal, string $jam): bool
    {
        return $this->hasConflict(
            $row->siswa_id,
            $row->guru_id,
            $row->guru_bk,
            $tanggal,
            $jam,
            $row->id
        );
    }
}
