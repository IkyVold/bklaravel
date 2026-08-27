<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\StatusPenanganan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 26 Agustus 2026 #6 untuk jalur API — lihat
 * Tests\Feature\Web\LaporanStatusPenangananValidationTest untuk konteks
 * lengkap. laporan_status_penanganan di sini nullable (laporan bisa
 * diedit tanpa mengirim field ini), tapi kalau dikirim wajib salah satu
 * dari StatusPenanganan::ALL.
 */
class LaporanStatusPenangananValidationTest extends TestCase
{
    use RefreshDatabase;

    private function konfirmasiKonseling(GuruBk $guru): Konseling
    {
        $siswa = Siswa::factory()->create();
        return Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
        ]);
    }

    public function test_status_penanganan_nilai_bebas_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $row = $this->konfirmasiKonseling($guru);
        Sanctum::actingAs($guru, ['guru']);

        $response = $this->putJson('/api/konseling/' . $row->id . '/laporan', [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => 'monitoring',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('konseling', ['id' => $row->id, 'status' => 'Proses']);
    }

    public function test_status_penanganan_master_diterima(): void
    {
        $guru = GuruBk::factory()->create();
        $row = $this->konfirmasiKonseling($guru);
        Sanctum::actingAs($guru, ['guru']);

        $response = $this->putJson('/api/konseling/' . $row->id . '/laporan', [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => StatusPenanganan::RUJUK,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('konseling', [
            'id' => $row->id,
            'status' => 'Selesai',
            'laporan_status_penanganan' => StatusPenanganan::RUJUK,
        ]);
    }
}
