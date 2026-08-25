<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 25 Agustus 2026, poin 6): KonselingReportService dulu
 * hanya mewajibkan & membuat sesi lanjutan saat laporan PERTAMA
 * (!$hasLaporan). Kalau laporan awal berstatus Selesai lalu diedit
 * (dalam window 72 jam) menjadi Monitoring, validasi wajib sesi lanjutan
 * tidak berjalan dan sesi lanjutan juga tidak dibuat — bisa terbentuk
 * status_penanganan=Monitoring tanpa sesi lanjutan sama sekali. Sekarang
 * yang dicek adalah "apakah konseling ini sudah punya sesi lanjutan
 * (child)", bukan "apakah ini laporan pertama".
 */
class LaporanMonitoringEditTest extends TestCase
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

        return [$row, $guru];
    }

    public function test_edit_laporan_menjadi_monitoring_tanpa_sesi_lanjutan_ditolak(): void
    {
        [$row, $guru] = $this->buatKonselingSiapLaporan();
        Sanctum::actingAs($guru, ['guru']);

        // Laporan pertama: status penanganan Selesai (bukan Monitoring),
        // jadi tidak butuh sesi lanjutan sama sekali.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_kesimpulan' => 'Kesimpulan awal konseling',
            'laporan_rekomendasi' => 'Rekomendasi awal konseling',
            'laporan_status_penanganan' => 'Selesai',
        ])->assertOk();

        $this->assertSame('Selesai', $row->fresh()->status);
        $this->assertSame(0, Konseling::where('pengajuan_sebelumnya_id', $row->id)->count());

        // Edit laporan (masih dalam window 72 jam) mengubah status
        // penanganan jadi Monitoring TANPA mengisi tanggal/jam lanjutan.
        // Harus ditolak, bukan diloloskan diam-diam.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_status_penanganan' => 'Monitoring',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Status Monitoring: isi tanggal & jam sesi lanjutan.',
            ]);

        // Tidak ada sesi lanjutan yang terbentuk dari percobaan yang gagal.
        $this->assertSame(0, Konseling::where('pengajuan_sebelumnya_id', $row->id)->count());
    }

    public function test_edit_laporan_menjadi_monitoring_dengan_tanggal_jam_membuat_sesi_lanjutan(): void
    {
        [$row, $guru] = $this->buatKonselingSiapLaporan();
        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_kesimpulan' => 'Kesimpulan awal konseling',
            'laporan_rekomendasi' => 'Rekomendasi awal konseling',
            'laporan_status_penanganan' => 'Selesai',
        ])->assertOk();

        $response = $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_status_penanganan' => 'Monitoring',
            'lanjutan_tanggal' => now()->addDays(3)->toDateString(),
            'lanjutan_jam' => '10:00',
        ])->assertOk();

        $this->assertStringContainsString('Sesi lanjutan telah dibuat.', $response->json('message'));
        $this->assertSame('Monitoring', $row->fresh()->laporan_status_penanganan);

        $child = Konseling::where('pengajuan_sebelumnya_id', $row->id)->first();
        $this->assertNotNull($child);
        $this->assertSame($row->siswa_id, $child->siswa_id);
    }

    public function test_edit_laporan_yang_sudah_punya_sesi_lanjutan_tidak_membuat_duplikat(): void
    {
        [$row, $guru] = $this->buatKonselingSiapLaporan();
        Sanctum::actingAs($guru, ['guru']);

        // Laporan pertama langsung Monitoring dengan sesi lanjutan lengkap.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_kesimpulan' => 'Kesimpulan awal konseling',
            'laporan_rekomendasi' => 'Rekomendasi awal konseling',
            'laporan_status_penanganan' => 'Monitoring',
            'lanjutan_tanggal' => now()->addDays(3)->toDateString(),
            'lanjutan_jam' => '10:00',
        ])->assertOk();

        $this->assertSame(1, Konseling::where('pengajuan_sebelumnya_id', $row->id)->count());

        // Edit laporan lagi (masih Monitoring, tanpa mengisi lanjutan_tanggal/jam
        // lagi) — tidak boleh ditolak dan tidak boleh membuat child kedua,
        // karena sesi lanjutan sudah ada.
        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_catatan_tambahan' => 'Update catatan saja',
        ])->assertOk();

        $this->assertSame(1, Konseling::where('pengajuan_sebelumnya_id', $row->id)->count());
    }
}
