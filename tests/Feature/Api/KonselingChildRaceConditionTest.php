<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Services\KonselingReportService;
use App\Support\StatusPenanganan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup revisi 27 Agustus 2026, poin 6: "Pembuatan sesi Monitoring masih
 * memiliki race condition kecil" — dua request laporan yang datang hampir
 * bersamaan untuk parent konseling yang sama secara teori bisa keduanya
 * lolos pengecekan "belum ada child" lalu keduanya membuat sesi lanjutan
 * (dua child untuk satu parent).
 *
 * PHPUnit/SQLite di test suite ini berjalan pada satu koneksi/satu thread,
 * jadi interleaving request sungguhan (dua request benar-benar bersamaan)
 * tidak bisa disimulasikan persis di sini — SQLite juga mengabaikan
 * FOR UPDATE (lihat catatan di ScheduleService::runLocked() dan
 * KonselingReportService::simpan()). Yang BISA dan PERLU diuji di level
 * test:
 *   1. Unique constraint di database (migration 2026_08_27_000001) benar
 *      ada dan benar-benar menolak dua baris dengan
 *      pengajuan_sebelumnya_id yang sama — ini lapisan pertahanan yang
 *      tetap berfungsi di database MANA PUN (termasuk saat lock baris
 *      gagal ter-acquire, mis. karena driver/isolasi transaksi berbeda).
 *   2. Kalau constraint itu sampai terlanggar, error yang keluar ke user
 *      adalah pesan yang aman (lewat RuntimeException), bukan
 *      QueryException/500 mentah.
 *   3. Alur normal (laporan dibuat berurutan, bukan race) tetap
 *      menghasilkan TEPAT SATU sesi lanjutan — regresi dari perilaku yang
 *      sudah benar sebelumnya (lihat juga LaporanMonitoringEditTest).
 */
class KonselingChildRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonselingSiapLaporan(): array
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
        ]);

        return [$row, $guru, $siswa];
    }

    public function test_unique_constraint_menolak_dua_child_dengan_parent_yang_sama(): void
    {
        [$parent, $guru, $siswa] = $this->buatKonselingSiapLaporan();

        $payload = [
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'kategori' => 'Lainnya',
            'deskripsi' => str_repeat('x', 25),
            'tanggal' => now()->addDays(3)->toDateString(),
            'jam' => '10:00',
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
            'pengajuan_sebelumnya_id' => $parent->id,
        ];

        // Child pertama untuk parent ini — harus berhasil.
        Konseling::create($payload);

        // Child KEDUA dengan pengajuan_sebelumnya_id yang SAMA — harus
        // ditolak database lewat unique constraint, bukan lolos begitu
        // saja (yang berarti satu parent punya dua sesi lanjutan).
        $this->expectException(QueryException::class);
        Konseling::create($payload);
    }

    public function test_percobaan_buat_child_kedua_secara_langsung_menghasilkan_pesan_aman_bukan_error_mentah(): void
    {
        [$parent, $guru, $siswa] = $this->buatKonselingSiapLaporan();

        // Simulasikan child pertama SUDAH ada di database (mis. dibuat
        // transaksi lain yang menang race), lalu paksa buatSesiLanjutan()
        // dipanggil lagi untuk parent yang sama lewat method protected —
        // ini mewakili skenario dua transaksi lolos pengecekan bersamaan
        // sebelum lapisan lockForUpdate() sempat menahan salah satunya.
        Konseling::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'kategori' => 'Lainnya',
            'deskripsi' => str_repeat('x', 25),
            'tanggal' => now()->addDays(3)->toDateString(),
            'jam' => '10:00',
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
            'pengajuan_sebelumnya_id' => $parent->id,
        ]);

        $service = app(KonselingReportService::class);
        $reflection = new \ReflectionMethod($service, 'buatSesiLanjutan');
        $reflection->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('konseling ini sudah mempunyai sesi lanjutan');

        $reflection->invoke($service, $parent, [
            'lanjutan_tanggal' => now()->addDays(5)->toDateString(),
            'lanjutan_jam' => '11:00',
            'laporan_rekomendasi' => 'Rekomendasi lanjutan kedua',
        ]);
    }

    public function test_laporan_berurutan_normal_tetap_menghasilkan_tepat_satu_sesi_lanjutan(): void
    {
        [$row, $guru] = $this->buatKonselingSiapLaporan();
        Sanctum::actingAs($guru, ['guru']);

        // Laporan pertama: Monitoring dengan sesi lanjutan lengkap —
        // membuat child pertama.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_kesimpulan' => 'Kesimpulan awal konseling',
            'laporan_rekomendasi' => 'Rekomendasi awal konseling',
            'laporan_status_penanganan' => 'Monitoring',
            'lanjutan_tanggal' => now()->addDays(3)->toDateString(),
            'lanjutan_jam' => '10:00',
        ])->assertOk();

        // Request kedua ke endpoint yang sama (berurutan, bukan race) —
        // service HARUS melihat child yang sudah ada (dihitung ulang di
        // dalam lock) dan TIDAK membuat child kedua walau request masih
        // mengirim data lanjutan_tanggal/jam.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_status_penanganan' => 'Monitoring',
            'lanjutan_tanggal' => now()->addDays(7)->toDateString(),
            'lanjutan_jam' => '13:00',
        ])->assertOk();

        $this->assertSame(1, Konseling::where('pengajuan_sebelumnya_id', $row->id)->count());
    }
}
