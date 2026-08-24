<?php

namespace App\Services;

use App\Models\Konseling;
use App\Models\Notifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu-satunya sumber aturan bisnis untuk menyimpan laporan konseling —
 * dipakai oleh Web/KonselingController@laporan dan Api/KonselingController@laporan.
 *
 * PERBAIKAN (revisi 24 Agustus 2026, poin 5): sebelumnya jalur API punya
 * logika laporan sendiri yang terpisah dari jalur Web — validasi
 * kesimpulan/rekomendasi bersifat nullable dan sama sekali tidak memeriksa
 * aturan "status penanganan Monitoring wajib sesi lanjutan". Akibatnya
 * konseling dengan kategori Monitoring bisa langsung ditandai Selesai lewat
 * API tanpa follow-up apa pun, padahal jalur Web sudah mewajibkannya.
 * Sekarang kedua controller hanya memanggil simpan() di sini — jangan
 * duplikasi logika laporan/Monitoring di controller manapun.
 */
class KonselingReportService
{
    public function __construct(private ScheduleService $schedule)
    {
    }

    /** Jam batas edit laporan setelah pertama kali disimpan. */
    private const WINDOW_HOURS = 72;

    /**
     * Simpan laporan konseling (buat baru atau edit dalam window), termasuk
     * membuat sesi lanjutan bila status penanganan Monitoring.
     *
     * @param  Konseling  $row  Baris konseling yang dilaporkan.
     * @param  array  $data  Field laporan: laporan_kesimpulan, laporan_rekomendasi,
     *                       laporan_status_penanganan, laporan_catatan_tambahan,
     *                       serta opsional buat_lanjutan/lanjutan_tanggal/lanjutan_jam/lanjutan_jenis.
     * @param  string  $namaPembuatLaporan  Nama user yang mengisi laporan (dicatat di laporan_dibuat_oleh).
     * @return string  Pesan sukses yang siap ditampilkan ke user.
     *
     * @throws \RuntimeException  Jika ada pelanggaran business rule; pesannya
     *                             sudah aman ditampilkan langsung ke user.
     */
    public function simpan(Konseling $row, array $data, string $namaPembuatLaporan): string
    {
        $hasLaporan = !empty($row->laporan_created_at) || !empty($row->laporan_kesimpulan);

        // --- Semua validasi business rule dilakukan SEBELUM ada perubahan
        // apa pun ke database, supaya tidak ada state setengah-tersimpan. ---

        if ($hasLaporan && $row->laporan_created_at) {
            $created = \Carbon\Carbon::parse($row->laporan_created_at);
            $jamBerlalu = $created->diffInMinutes(now()) / 60;
            if ($jamBerlalu > self::WINDOW_HOURS) {
                throw new \RuntimeException(
                    'Laporan terkunci. Batas edit ' . self::WINDOW_HOURS . ' jam setelah pertama disimpan sudah lewat.'
                );
            }
        }

        if (!$hasLaporan && (!$row->isKonfirmasi() || ($row->status ?? '') === 'Dibatalkan')) {
            throw new \RuntimeException('Laporan hanya untuk sesi yang sudah dikonfirmasi dan belum dibatalkan.');
        }

        // Kesimpulan, rekomendasi & status penanganan wajib diisi untuk
        // laporan PERTAMA (menyelesaikan konseling). Dicek di sini —
        // bukan hanya lewat rule Validator di controller — supaya jalur
        // mana pun yang memanggil service ini tidak bisa lolos dengan
        // field kosong, meski suatu saat ada jalur ketiga.
        if (!$hasLaporan) {
            if (empty($data['laporan_kesimpulan']) || empty($data['laporan_rekomendasi']) || empty($data['laporan_status_penanganan'])) {
                throw new \RuntimeException('Kesimpulan, rekomendasi, dan status penanganan wajib diisi untuk menyelesaikan konseling.');
            }
        }

        // Sesi lanjutan wajib tanggal & jam kalau status penanganan Monitoring.
        // Ini aturan inti yang hilang di jalur API sebelumnya (poin 5).
        $statusPenanganan = $data['laporan_status_penanganan'] ?? $row->laporan_status_penanganan;
        $buatLanjutan = !empty($data['buat_lanjutan']) || $statusPenanganan === 'Monitoring';
        $lanjutanLengkap = !empty($data['lanjutan_tanggal']) && !empty($data['lanjutan_jam']);

        if ($statusPenanganan === 'Monitoring' && !$hasLaporan && !$lanjutanLengkap) {
            throw new \RuntimeException('Status Monitoring: isi tanggal & jam sesi lanjutan.');
        }

        // --- Semua valid. Simpan laporan, ubah status, buat sesi lanjutan,
        // dan notifikasi dalam satu transaksi — gagal satu, rollback semua. ---

        return DB::transaction(function () use ($row, $data, $hasLaporan, $buatLanjutan, $lanjutanLengkap, $namaPembuatLaporan) {
            $row->laporan_kesimpulan = $data['laporan_kesimpulan'] ?? $row->laporan_kesimpulan;
            $row->laporan_rekomendasi = $data['laporan_rekomendasi'] ?? $row->laporan_rekomendasi;
            $row->laporan_status_penanganan = $data['laporan_status_penanganan'] ?? $row->laporan_status_penanganan;
            $row->laporan_catatan_tambahan = $data['laporan_catatan_tambahan'] ?? '-';

            if ($hasLaporan) {
                $row->save();
                $msg = 'Laporan berhasil diperbarui.';
            } else {
                $row->laporan_tanggal = now()->toDateString();
                $row->laporan_waktu = now()->format('H:i:s');
                $row->laporan_dibuat_oleh = $namaPembuatLaporan;
                $row->laporan_created_at = now();
                $row->status = 'Selesai';
                $row->save();
                $msg = 'Laporan disimpan & konseling diselesaikan.';
            }

            if ($buatLanjutan && $lanjutanLengkap && !$hasLaporan) {
                $this->buatSesiLanjutan($row, $data);
                $msg .= ' Sesi lanjutan telah dibuat.';
            }

            return $msg;
        });
    }

    /**
     * Buat sesi lanjutan dari parent yang baru saja Selesai. Guru/identitas
     * sesi lanjutan SENGAJA diambil dari parent (guru_id/guru_bk konseling
     * asli), bukan dari user yang sedang login — karena laporan (khususnya
     * lewat API) bisa saja diisi oleh Admin atas nama Guru BK yang
     * bersangkutan (lihat assertGuruCanManageKonseling). Sesi lanjutan
     * harus tetap tercatat milik Guru BK pemilik konsultasi asli, bukan
     * milik Admin yang mengisi laporan.
     */
    protected function buatSesiLanjutan(Konseling $parent, array $data): Konseling
    {
        $deskripsi = 'Sesi lanjutan dari konseling #' . $parent->id . '. ' . ($data['laporan_rekomendasi'] ?? '');
        $deskripsi = mb_substr(trim($deskripsi), 0, 500);
        if (mb_strlen($deskripsi) < 20) {
            $deskripsi = str_pad($deskripsi, 20, '.');
        }

        // Sesi lanjutan juga harus lolos cek bentrok — Guru BK yang sama
        // bisa saja sudah menjadwalkan sesi lain di tanggal/jam tersebut.
        // Dilempar sebagai exception biasa supaya DB::transaction() di
        // simpan() otomatis rollback; ditangkap & ditampilkan di caller.
        if ($this->schedule->hasConflict(
            $parent->siswa_id,
            $parent->guru_id,
            $parent->guru_bk,
            $data['lanjutan_tanggal'],
            $data['lanjutan_jam']
        )) {
            throw new \RuntimeException('Sesi lanjutan gagal dibuat: jadwal bentrok dengan konseling lain pada tanggal/jam tersebut.');
        }

        $payload = [
            'siswa_id' => $parent->siswa_id,
            'guru_bk' => $parent->guru_bk,
            'jenis' => $data['lanjutan_jenis'] ?? ($parent->jenis === 'Daring' ? 'Daring' : 'Luring'),
            'kategori' => $parent->kategori ?? 'Lainnya',
            'deskripsi' => $deskripsi,
            'tanggal' => $data['lanjutan_tanggal'],
            'jam' => $data['lanjutan_jam'],
            'kelas_siswa' => $parent->kelas_siswa,
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi', // jadwal sudah ditentukan guru
            'tanggal_konfirmasi' => $data['lanjutan_tanggal'],
            'jam_konfirmasi' => $data['lanjutan_jam'],
            'created_at' => now(),
        ];
        if (Schema::hasColumn('konseling', 'guru_id') && $parent->guru_id) {
            $payload['guru_id'] = $parent->guru_id;
        }
        if (Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            $payload['pengajuan_sebelumnya_id'] = $parent->id;
        }

        $child = Konseling::create($payload);

        // Notifikasi siswa jika memungkinkan — pakai helper Notifikasi::buatUntuk()
        // (cast 'data' => array sudah ditangani model, tidak perlu json_encode manual).
        try {
            $nis = $parent->siswa->nis ?? null;
            if ($nis) {
                Notifikasi::buatUntuk(
                    (string) $nis,
                    'siswa',
                    'Sesi Konseling Lanjutan',
                    'Guru BK menjadwalkan sesi lanjutan pada ' . $data['lanjutan_tanggal'] . ' jam ' . $data['lanjutan_jam'],
                    'konseling',
                    $child->id,
                );
            }
        } catch (\Throwable $e) {
        }

        return $child;
    }
}
