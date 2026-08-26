<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateStatusConfirmedCancelTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonselingProses(string $statusKonfirmasi): array
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Proses',
            'status_konfirmasi' => $statusKonfirmasi,
        ]);

        return [$row, $guru];
    }

    /** @dataProvider statusTerkonfirmasiProvider */
    public function test_konseling_yang_sudah_dikonfirmasi_tidak_bisa_dibatalkan_lewat_api(string $statusKonfirmasi): void
    {
        [$row, $guru] = $this->buatKonselingProses($statusKonfirmasi);

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/status", [
            'status' => 'Dibatalkan',
            'alasan_batal' => 'Coba batalkan setelah dikonfirmasi',
        ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Jadwal yang sudah dikonfirmasi tidak dapat dibatalkan. Gunakan laporan untuk menyelesaikan sesi.',
            ]);

        $this->assertSame('Proses', $row->fresh()->status);
    }

    public static function statusTerkonfirmasiProvider(): array
    {
        return [
            ['Terkonfirmasi'],
            ['Dikonfirmasi'],
            ['Tervalidasi'],
        ];
    }

    public function test_konseling_proses_yang_belum_dikonfirmasi_masih_bisa_dibatalkan_lewat_api(): void
    {
        [$row, $guru] = $this->buatKonselingProses('Belum Dikonfirmasi');

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/status", [
            'status' => 'Dibatalkan',
            'alasan_batal' => 'Siswa mengundurkan diri',
        ])->assertOk();

        $this->assertSame('Dibatalkan', $row->fresh()->status);
    }

    public function test_admin_juga_tidak_bisa_membatalkan_konseling_yang_sudah_dikonfirmasi(): void
    {
        [$row] = $this->buatKonselingProses('Terkonfirmasi');

        $admin = \App\Models\Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson("/api/konseling/{$row->id}/status", [
            'status' => 'Dibatalkan',
            'alasan_batal' => 'Coba lewat admin',
        ])->assertStatus(400);

        $this->assertSame('Proses', $row->fresh()->status);
    }
}
