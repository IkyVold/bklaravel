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
        // PERBAIKAN (revisi 25 Agustus 2026, poin 8): dulu filter guru di
        // sini pakai OR independen (guru_id COCOK ATAU guru_bk COCOK NAMA),
        // persis pola bug yang sama dengan listByGuru() (poin 7) dan
        // ownership check lama (poin 24 Agustus, poin 8) — kalau ada dua
        // Guru BK dengan nama sama persis, jadwal Guru A bisa dianggap
        // bentrok dengan jadwal Guru B hanya karena namanya sama, padahal
        // guru_id keduanya berbeda. Sekarang begitu $guruId diberikan, itu
        // SATU-SATUNYA sumber kebenaran untuk mencocokkan guru; fallback
        // nama HANYA dipakai untuk mencocokkan baris lama yang guru_id-nya
        // memang null (data sebelum kolom guru_id ada) — konsisten dengan
        // guruOwnsKonseling() di AuthorizesBk.
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

    /**
     * PERBAIKAN (revisi 26 Agustus 2026, poin 8): hasConflict() dulu selalu
     * dipanggil terpisah dari Konseling::create()/save() di controller —
     * pola "cek dulu → baru simpan" tanpa penguncian apa pun. Dua request
     * yang datang hampir bersamaan (mis. dua siswa mengajukan slot yang
     * sama, atau siswa & walk-in bentrok) bisa SAMA-SAMA menjalankan cek
     * konflik sebelum salah satunya sempat menyimpan baris barunya —
     * keduanya melihat slot masih kosong dan keduanya lolos.
     *
     * Metode ini membungkus "cek konflik → simpan/ubah" milik pemanggil
     * dalam satu transaksi DB, dan sebelum callback dijalankan, mengunci
     * (SELECT ... FOR UPDATE) baris GuruBk dan/atau Siswa yang terlibat.
     * Baris guru/siswa itu sendiri TIDAK diubah isinya — kuncinya dipakai
     * murni sebagai mutex, supaya request kedua yang menyentuh guru/siswa
     * yang sama dipaksa menunggu sampai transaksi pertama selesai. Begitu
     * request kedua akhirnya berjalan, hasConflict() di dalamnya akan
     * melihat baris yang baru saja disimpan oleh request pertama.
     *
     * Locking di sini SENGAJA berbasis baris GuruBk/Siswa (bukan baris
     * Konseling) karena slot yang baru diajukan mungkin belum punya baris
     * Konseling sama sekali saat pengecekan berjalan — mengunci sesuatu
     * yang belum ada tidak mencegah race condition. Guru/siswa sudah pasti
     * ada sebelum pengajuan dibuat, jadi baris itulah yang dijadikan titik
     * sinkronisasi.
     *
     * Urutan penguncian (GuruBk dulu, baru Siswa; masing-masing diurutkan
     * naik berdasarkan id) SENGAJA dibuat selalu sama di semua pemanggil,
     * supaya dua transaksi yang saling melibatkan guru/siswa berbeda tidak
     * saling menunggu satu sama lain secara melingkar (deadlock).
     *
     * Pada koneksi MySQL (produksi) ini benar-benar mengunci baris.
     * Pada SQLite (dipakai test), grammar SQLite mengabaikan klausa
     * FOR UPDATE dengan aman (tidak error) — transaksi tetap berjalan
     * seperti biasa, hanya tanpa penguncian baris eksplisit tambahan.
     *
     * @template T
     * @param  \Closure(): T  $callback  Berisi hasConflict()/hasConflictFor()
     *                                    lalu create()/save() milik pemanggil.
     * @return T
     */
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
