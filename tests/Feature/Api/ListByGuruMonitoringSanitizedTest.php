<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListByGuruMonitoringSanitizedTest extends TestCase
{
    use RefreshDatabase;

    public function test_kepsek_tidak_menerima_isi_konsultasi_mentah_lewat_konseling_bk(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Ahmad']);

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'deskripsi' => 'Isi curhat rahasia siswa',
            'laporan_kesimpulan' => 'Kesimpulan rahasia',
            'laporan_rekomendasi' => 'Rekomendasi rahasia',
            'laporan_catatan_tambahan' => 'Catatan rahasia',
        ]);

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $data = collect($response->json('data'));
        $item = $data->firstWhere('id', $row->id);

        $this->assertNotNull($item, 'Kepsek tetap harus bisa melihat data administratif untuk monitoring.');
        $this->assertArrayNotHasKey('deskripsi', $item);
        $this->assertArrayNotHasKey('laporan_kesimpulan', $item);
        $this->assertArrayNotHasKey('laporan_rekomendasi', $item);
        $this->assertArrayNotHasKey('laporan_catatan_tambahan', $item);
    }

    public function test_admin_tidak_menerima_isi_konsultasi_mentah_lewat_konseling_bk(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Ahmad']);

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'deskripsi' => 'Isi curhat rahasia siswa',
            'laporan_kesimpulan' => 'Kesimpulan rahasia',
        ]);

        $admin = \App\Models\Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $data = collect($response->json('data'));
        $item = $data->firstWhere('id', $row->id);

        $this->assertNotNull($item);
        $this->assertArrayNotHasKey('deskripsi', $item);
        $this->assertArrayNotHasKey('laporan_kesimpulan', $item);
    }

    public function test_guru_tetap_menerima_isi_konsultasi_lengkap_miliknya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Ahmad']);

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'deskripsi' => 'Isi curhat siswa',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $data = collect($response->json('data'));
        $item = $data->firstWhere('id', $row->id);

        $this->assertNotNull($item);
        $this->assertArrayHasKey('deskripsi', $item, 'Guru BK pemilik tetap harus melihat isi konsultasi lengkap.');
        $this->assertSame('Isi curhat siswa', $item['deskripsi']);
    }
}
