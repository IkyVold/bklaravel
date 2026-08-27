<?php

namespace App\Services;

use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Support\StatusPenanganan;
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
        // PERBAIKAN (revisi 27 Agustus 2026, poin 6): seluruh alur simpan()
        // sekarang dibungkus SATU transaksi yang dimulai dengan MENGUNCI
        // baris parent ($row) lebih dulu lewat lockForUpdate(), SEBELUM
        // $hasChildLanjutan dihitung. Sebelumnya urutannya adalah:
        //   1. Hitung $hasChildLanjutan (query biasa, tanpa lock)
        //   2. ...validasi...
        //   3. DB::transaction() BARU dimulai di sini untuk simpan+buat child
        // Antara langkah 1 dan 3 ada celah waktu tanpa lock sama sekali.
        // Kalau dua request laporan untuk PARENT YANG SAMA datang hampir
        // bersamaan, keduanya bisa lolos langkah 1 dengan hasil
        // "belum ada child", lalu keduanya lolos ke langkah 3 dan
        // masing-masing membuat sesi lanjutan sendiri — dua child untuk
        // satu parent.
        //
        // Sekarang lockForUpdate() pada baris parent dipanggil PALING AWAL
        // di dalam transaksi. Pada MySQL (produksi), transaksi kedua yang
        // mencoba mengunci parent yang sama akan BENAR-BENAR menunggu
        // (blocking) sampai transaksi pertama commit/rollback — begitu
        // transaksi kedua lanjut, ia menghitung ulang $hasChildLanjutan dan
        // akan melihat child yang baru saja dibuat transaksi pertama,
        // sehingga tidak ikut membuat child kedua. Pada SQLite (dipakai
        // test), FOR UPDATE diabaikan dengan aman (lihat catatan yang sama
        // di ScheduleService::runLocked()) — race condition lintas-thread
        // sungguhan tidak bisa disimulasikan di sana, tapi urutan
        // baca-lalu-tulis yang benar (baca DALAM transaksi, setelah lock)
        // tetap tervalidasi oleh test yang memanggil simpan() dua kali
        // berurutan pada parent yang sama.
        //
        // Sebagai lapisan pertahanan terakhir, unique index database pada
        // pengajuan_sebelumnya_id (migration 2026_08_27_000001) tetap
        // dipertahankan — kalau karena sebab apa pun (mis. lock gagal
        // ter-acquire di driver DB tertentu) dua insert child tetap lolos
        // sampai ke database, insert kedua akan gagal dengan
        // QueryException yang ditangkap di buatSesiLanjutan() dan diubah
        // jadi pesan yang aman ditampilkan ke user, bukan error 500 mentah.
        return DB::transaction(function () use ($row, $data, $namaPembuatLaporan) {
            $row = Konseling::where('id', $row->id)->lockForUpdate()->firstOrFail();

            $hasLaporan = !empty($row->laporan_created_at) || !empty($row->laporan_kesimpulan);

            // PERBAIKAN (revisi 25 Agustus 2026, poin 6): sebelumnya kewajiban
            // sesi lanjutan untuk status Monitoring hanya diperiksa/ dibuat
            // ketika !$hasLaporan (laporan PERTAMA). Akibatnya, laporan awal
            // dengan status penanganan Selesai lalu diedit (dalam window 72
            // jam) menjadi Monitoring tidak pernah lolos validasi wajib sesi
            // lanjutan DAN tidak pernah membuat sesi lanjutan — karena
            // $hasLaporan sudah true. Bisa terbentuk status_penanganan =
            // Monitoring tanpa sesi lanjutan sama sekali.
            //
            // Sekarang yang menentukan wajib/tidaknya sesi lanjutan bukan lagi
            // "apakah ini laporan pertama", melainkan "apakah konseling ini
            // SUDAH punya sesi lanjutan (child)". child ditandai lewat kolom
            // pengajuan_sebelumnya_id yang menunjuk ke $row->id (lihat
            // buatSesiLanjutan()). Jadi:
            //  - Laporan pertama, status Monitoring → wajib buat sesi lanjutan
            //    (seperti sebelumnya, karena belum ada child).
            //  - Edit laporan yang MENGUBAH status jadi Monitoring dan BELUM
            //    punya child → tetap wajib mengisi & membuat sesi lanjutan.
            //  - Edit laporan yang statusnya sudah Monitoring dan child SUDAH
            //    ada (dibuat sebelumnya) → tidak diminta lagi / tidak dibuat
            //    duplikat.
            $hasChildLanjutan = Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')
                && Konseling::where('pengajuan_sebelumnya_id', $row->id)->exists();

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

            // PERBAIKAN (revisi 26 Agustus 2026, poin 6): lapisan pertahanan
            // terakhir. Validasi Rule::in() di Web/API sudah menolak nilai
            // di luar StatusPenanganan::ALL, tapi service ini bisa saja
            // dipanggil dari jalur lain di masa depan — jadi diperiksa ulang
            // di sini juga, bukan hanya dipercaya dari controller.
            if (!empty($data['laporan_status_penanganan']) && !in_array($data['laporan_status_penanganan'], StatusPenanganan::ALL, true)) {
                throw new \RuntimeException('Status penanganan tidak valid.');
            }

            // Sesi lanjutan wajib tanggal & jam kalau status penanganan Monitoring
            // dan belum ada sesi lanjutan (child) untuk konseling ini.
            $statusPenanganan = $data['laporan_status_penanganan'] ?? $row->laporan_status_penanganan;
            $buatLanjutan = !empty($data['buat_lanjutan']) || $statusPenanganan === StatusPenanganan::MONITORING;
            $lanjutanLengkap = !empty($data['lanjutan_tanggal']) && !empty($data['lanjutan_jam']);

            if ($statusPenanganan === StatusPenanganan::MONITORING && !$hasChildLanjutan && !$lanjutanLengkap) {
                throw new \RuntimeException('Status Monitoring: isi tanggal & jam sesi lanjutan.');
            }

            // --- Semua valid. Simpan laporan, ubah status, buat sesi lanjutan,
            // dan notifikasi — masih dalam transaksi & lock yang sama di atas,
            // gagal satu, rollback semua. ---

            $row->laporan_kesimpulan = $data['laporan_kesimpulan'] ?? $row->laporan_kesimpulan;
            $row->laporan_rekomendasi = $data['laporan_rekomendasi'] ?? $row->laporan_rekomendasi;
            $row->laporan_status_penanganan = $data['laporan_status_penanganan'] ?? $row->laporan_status_penanganan;

            // PERBAIKAN (revisi 26 Agustus 2026, poin 7): sebelumnya baris
            // ini langsung fallback ke '-' kalau request tidak mengirim
            // laporan_catatan_tambahan. Untuk laporan PERTAMA itu wajar
            // (memang belum ada catatan), tapi untuk EDIT laporan yang
            // sudah punya catatan, request yang tidak menyertakan field ini
            // (mis. form/consumer API lama yang tidak mengirim field
            // tersebut) membuat catatan lama ikut terhapus jadi '-' tanpa
            // pengguna sadar. Sekarang fallback-nya ke nilai lama pada
            // $row dulu, baru ke '-' kalau memang belum pernah ada catatan
            // sama sekali (laporan pertama / row baru).
            $row->laporan_catatan_tambahan = $data['laporan_catatan_tambahan']
                ?? $row->laporan_catatan_tambahan
                ?? '-';

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

            if ($buatLanjutan && $lanjutanLengkap && !$hasChildLanjutan) {
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

        // PERBAIKAN (revisi 27 Agustus 2026, poin 6): lockForUpdate() pada
        // simpan() sudah mencegah hampir semua kasus race condition, tapi
        // sebagai lapisan pertahanan TERAKHIR (mis. kalau suatu saat ada
        // jalur lain yang memanggil buatSesiLanjutan() di luar simpan(),
        // atau driver DB tertentu tidak benar-benar mengunci baris),
        // pelanggaran unique constraint pada pengajuan_sebelumnya_id
        // (migration 2026_08_27_000001) ditangkap di sini dan diubah jadi
        // pesan yang aman ditampilkan ke user — bukan error 500 mentah
        // dari QueryException.
        try {
            $child = Konseling::create($payload);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new \RuntimeException(
                    'Sesi lanjutan gagal dibuat: konseling ini sudah mempunyai sesi lanjutan (kemungkinan dibuat lewat request lain yang hampir bersamaan).'
                );
            }
            throw $e;
        }

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

    /**
     * Deteksi pelanggaran unique constraint lintas driver (MySQL kode
     * error 1062, SQLite/Postgres pesan mengandung "UNIQUE constraint"/
     * "duplicate key"). Dipakai HANYA untuk lapisan pertahanan terakhir di
     * buatSesiLanjutan() — lihat komentar di sana.
     */
    private function isUniqueConstraintViolation(\Illuminate\Database\QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;
        if ($sqlState === '23000' || $driverCode === 1062) {
            return true;
        }

        return str_contains(strtolower($e->getMessage()), 'unique constraint')
            || str_contains(strtolower($e->getMessage()), 'duplicate key');
    }
}
