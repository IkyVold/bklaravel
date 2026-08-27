<?php

namespace App\Console\Commands;

use App\Models\Siswa;
use App\Support\TempPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LATAR BELAKANG: migration shorten_nis_to_4_digits menolak data siswa
 * yang nis-nya lebih dari 4 karakter (lihat preflight check di migration
 * tsb). Ditemukan di database production bahwa sebagian siswa (angkatan
 * kelas X yang baru) ternyata memiliki NISN nasional 10 digit tersimpan
 * di kolom `nis`, bukan NIS lokal sekolah — kemungkinan salah input saat
 * pendaftaran. Sekolah belum punya NIS lokal resmi untuk siswa-siswa ini,
 * sehingga command ini MEMBUAT NIS lokal baru secara berurutan (lanjut
 * dari NIS valid tertinggi yang sudah ada), sekaligus:
 *
 *   1. Mengarsipkan nilai 10 digit lama ke kolom `nisn` (lihat migration
 *      add_nisn_to_siswa) supaya tidak hilang untuk keperluan
 *      administratif lain (mis. pelaporan Dapodik).
 *   2. Membuatkan password awal baru yang ACAK (bukan berdasar NIS lama
 *      atau baru — sama seperti pola TempPassword yang dipakai saat
 *      membuat siswa baru) dan menandai must_change_password = true,
 *      karena NIS mereka (identitas login) berubah total.
 *
 * Dijalankan HANYA SEKALI secara manual oleh Admin/developer, BUKAN
 * bagian dari alur migration otomatis — karena keputusan "berapa NIS
 * baru untuk siapa" sebaiknya bisa direview dulu (lewat --dry-run)
 * sebelum benar-benar mengubah data produksi, dan hasilnya (NIS +
 * password baru per siswa) harus disampaikan manual ke siswa yang
 * bersangkutan.
 */
class FixInvalidNisLength extends Command
{
    protected $signature = 'app:fix-invalid-nis
        {--dry-run : Tampilkan rencana perubahan tanpa menyimpan apa pun ke database}
        {--export= : Path file CSV untuk menyimpan hasil (nis lama, nis baru, password baru) supaya bisa dibagikan ke siswa}';

    protected $description = 'Perbaiki siswa.nis yang lebih dari 4 karakter (mis. NISN tertukar dengan NIS lokal): arsipkan ke nisn, buat NIS lokal baru & password baru.';

    public function handle(): int
    {
        $this->ensureNisnColumnExists();

        $bermasalah = Siswa::whereRaw('CHAR_LENGTH(nis) > 4')
            ->orderBy('kelas')
            ->orderBy('nama')
            ->get();

        if ($bermasalah->isEmpty()) {
            $this->info('Tidak ada siswa dengan NIS lebih dari 4 karakter. Tidak ada yang perlu diperbaiki.');
            return self::SUCCESS;
        }

        $nisTerakhir = (int) (Siswa::whereRaw('CHAR_LENGTH(nis) = 4')->max(DB::raw('CAST(nis AS UNSIGNED)')) ?? 0);

        $this->info("Ditemukan {$bermasalah->count()} siswa dengan NIS > 4 karakter.");
        $this->info("NIS lokal valid tertinggi saat ini: {$nisTerakhir}. NIS baru akan mulai dari " . ($nisTerakhir + 1) . '.');
        $this->newLine();

        $rencana = [];
        $nisBerikutnya = $nisTerakhir + 1;

        foreach ($bermasalah as $siswa) {
            $nisBaru = (string) $nisBerikutnya;
            if (strlen($nisBaru) > 4) {
                $this->error("Berhenti: NIS baru {$nisBaru} sudah lebih dari 4 digit (kehabisan slot 0001-9999). Perlu strategi lain untuk siswa yang tersisa.");
                return self::FAILURE;
            }

            $passwordBaru = TempPassword::generate();

            $rencana[] = [
                'siswa' => $siswa,
                'nisn_lama' => $siswa->nis,
                'nis_baru' => $nisBaru,
                'password_baru' => $passwordBaru,
            ];

            $nisBerikutnya++;
        }

        $this->table(
            ['ID', 'Nama', 'Kelas', 'NIS Lama (→nisn)', 'NIS Baru', 'Password Baru'],
            collect($rencana)->map(fn ($r) => [
                $r['siswa']->id,
                $r['siswa']->nama,
                $r['siswa']->kelas,
                $r['nisn_lama'],
                $r['nis_baru'],
                $r['password_baru'],
            ])
        );

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('Mode --dry-run: TIDAK ADA perubahan yang disimpan ke database. Jalankan tanpa --dry-run untuk benar-benar menerapkan.');
            $this->maybeExport($rencana);
            return self::SUCCESS;
        }

        if (!$this->confirm('Terapkan perubahan di atas ke database sekarang? Tindakan ini TIDAK bisa di-undo secara otomatis.', false)) {
            $this->comment('Dibatalkan. Tidak ada perubahan yang disimpan.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rencana) {
            foreach ($rencana as $r) {
                $siswa = $r['siswa'];
                $siswa->nisn = $r['nisn_lama'];
                $siswa->nis = $r['nis_baru'];
                $siswa->password = $r['password_baru']; // trigger setPasswordAttribute (hash + naikkan password_version)
                $siswa->must_change_password = true;
                $siswa->save();
            }
        });

        $this->newLine();
        $this->info("Selesai. {$bermasalah->count()} siswa telah diperbarui: NIS lama diarsipkan ke kolom nisn, NIS lokal baru & password baru dibuat.");
        $this->warn('PENTING: sampaikan NIS baru dan password baru ke masing-masing siswa secara manual (mis. lewat wali kelas). Password ini tidak disimpan dalam bentuk plain text di mana pun setelah perintah ini selesai.');

        $this->maybeExport($rencana);

        return self::SUCCESS;
    }

    private function ensureNisnColumnExists(): void
    {
        if (!Schema::hasColumn('siswa', 'nisn')) {
            Schema::table('siswa', function ($table) {
                $table->string('nisn', 20)->nullable()->after('nis');
            });
            $this->comment('Kolom nisn belum ada, sudah dibuat otomatis.');
        }
    }

    private function maybeExport(array $rencana): void
    {
        $path = $this->option('export');
        if (!$path) {
            return;
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, ['id', 'nama', 'kelas', 'nisn_lama', 'nis_baru', 'password_baru']);
        foreach ($rencana as $r) {
            fputcsv($handle, [
                $r['siswa']->id,
                $r['siswa']->nama,
                $r['siswa']->kelas,
                $r['nisn_lama'],
                $r['nis_baru'],
                $r['password_baru'],
            ]);
        }
        fclose($handle);

        $this->info("Rencana/hasil disimpan ke: {$path}");
        $this->warn('File ini berisi PASSWORD DALAM BENTUK PLAIN TEXT. Bagikan ke wali kelas/siswa lewat jalur aman, lalu HAPUS file ini setelah selesai dibagikan.');
    }
}