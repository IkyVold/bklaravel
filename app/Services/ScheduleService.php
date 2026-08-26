<?php

namespace App\Services;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber aturan bentrok jadwal konseling — dipakai oleh
 * pengajuan web, API, konfirmasi (web & API), walk-in, dan sesi lanjutan.
 * Jangan duplikasi query conflict-check di controller lain; panggil
 * service ini agar aturan bentrok selalu konsisten di semua jalur.
 */
class ScheduleService
{
    public const DEFAULT_DURATION_MINUTES = 60;

    /**
     * True jika siswa ATAU guru yang bersangkutan sudah mempunyai konseling
     * aktif (belum Dibatalkan/Ditolak/Selesai) pada tanggal yang sama dengan
     * interval waktu yang overlap terhadap [$jam, $jam + $durasiMenit).
     *
     * @param  int|null  $durasiMenit  Durasi sesi baru dalam menit. Null → DEFAULT_DURATION_MINUTES.
     * @param  int|null  $excludeId  ID konseling yang sedang diedit (dikecualikan dari cek)
     */
    public function hasConflict(
        ?int $siswaId,
        ?int $guruId,
        ?string $guruBk,
        string $tanggal,
        string $jam,
        ?int $durasiMenit = null,
        ?int $excludeId = null
    ): bool {
        $query = Konseling::whereDate('tanggal', $tanggal)
            ->whereNotIn('status', ['Dibatalkan', 'Ditolak', 'Selesai'])
            ->where(function ($q) use ($siswaId, $guruId, $guruBk) {
                $q->where(function ($qq) use ($siswaId) {
                    if ($siswaId) {
                        $qq->orWhere('siswa_id', $siswaId);
                    }
                });
                if ($guruId) {
                    $q->orWhere('guru_id', $guruId);
                    if ($guruBk) {
                        $q->orWhere(function ($qg) use ($guruBk) {
                            $qg->whereNull('guru_id')->where('guru_bk', $guruBk);
                        });
                    }
                } elseif ($guruBk) {
                    // Tidak ada guruId sama sekali — hanya kasus data lama.
                    $q->orWhere(function ($qg) use ($guruBk) {
                        $qg->whereNull('guru_id')->where('guru_bk', $guruBk);
                    });
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $newStart = strtotime($jam);
        $newEnd = $newStart + ($durasiMenit ?? self::DEFAULT_DURATION_MINUTES) * 60;

        foreach ($query->get() as $existing) {
            $existingStart = strtotime((string) $existing->jam);
            $existingEnd = $existingStart + ($existing->durasi_menit ?? self::DEFAULT_DURATION_MINUTES) * 60;

            // Dua interval overlap jika salah satu mulai sebelum yang lain
            // berakhir, di kedua arah. Batas persis bersentuhan (mis. sesi A
            // berakhir 10.00 tepat saat sesi B mulai 10.00) TIDAK dianggap
            // bentrok — dibuat strict (<) supaya sesi back-to-back tetap
            // bisa dijadwalkan.
            if ($newStart < $existingEnd && $existingStart < $newEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sama seperti hasConflict(), tapi langsung menerima instance Konseling
     * (dipakai saat konfirmasi/reschedule — otomatis exclude dirinya
     * sendiri). Durasi default memakai durasi tersimpan pada $row kecuali
     * $durasiMenit diberikan eksplisit (mis. saat konfirmasi sekaligus
     * mengubah durasi sesi).
     */
    public function hasConflictFor(Konseling $row, string $tanggal, string $jam, ?int $durasiMenit = null): bool
    {
        return $this->hasConflict(
            $row->siswa_id,
            $row->guru_id,
            $row->guru_bk,
            $tanggal,
            $jam,
            $durasiMenit ?? $row->durasi_menit,
            $row->id
        );
    }

    public function runLocked(?int $guruId, ?int $siswaId, \Closure $callback)
    {
        return DB::transaction(function () use ($guruId, $siswaId, $callback) {
            $locks = [];
            if ($guruId) {
                $locks[] = [GuruBk::class, $guruId];
            }
            if ($siswaId) {
                $locks[] = [Siswa::class, $siswaId];
            }

            // Urutan tetap: model class (GuruBk sebelum Siswa secara
            // alfabetis), lalu id menaik — mencegah deadlock antar
            // transaksi yang saling bersilangan guru/siswa-nya.
            usort($locks, function (array $a, array $b): int {
                return $a[0] === $b[0] ? $a[1] <=> $b[1] : strcmp($a[0], $b[0]);
            });

            foreach ($locks as [$modelClass, $id]) {
                $modelClass::where('id', $id)->lockForUpdate()->first();
            }

            return $callback();
        });
    }
}
