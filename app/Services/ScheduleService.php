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
     * PERBAIKAN (revisi 24 Agustus 2026, poin 11): sebelumnya bentrok hanya
     * dideteksi kalau jam MULAI persis sama (where('jam', $jam)). Kalau
     * sesi berdurasi 60 menit, sesi jam 10.00 dan sesi jam 10.30 jelas
     * overlap tapi tidak akan terdeteksi. Sesi tanpa 'durasi_menit' terisi
     * (data lama, atau jalur yang belum mengirim durasi) dianggap memakai
     * durasi default ini — sama seperti pola DEFAULT_DURATION_MINUTES yang
     * sudah dipakai JadwalRutinController untuk slot tanpa jam_selesai.
     */
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
        // PENTING: gunakan whereDate(), bukan where('tanggal', ...). Kolom
        // 'tanggal' di-cast sebagai 'date' pada model Konseling, dan Eloquent
        // secara default menyimpan cast tanggal dengan format lengkap
        // "Y-m-d H:i:s" (bukan "Y-m-d") kecuali format eksplisit diberikan.
        // Perbandingan string mentah where('tanggal', 'Y-m-d') tidak akan
        // pernah cocok dengan nilai tersimpan "Y-m-d 00:00:00", sehingga
        // bentrok jadwal tidak pernah terdeteksi. whereDate() membandingkan
        // hanya bagian tanggalnya sehingga aman terhadap format penyimpanan.
        //
        // Filter jam TIDAK lagi dilakukan di query ini (lihat poin 11 di
        // atas) — kandidat yang cocok siswa/guru pada tanggal tsb diambil
        // semua, lalu overlap interval dihitung di PHP seperti
        // JadwalRutinController::assertNoOverlap().
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
                }
                if ($guruBk) {
                    $q->orWhere('guru_bk', $guruBk);
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
}
