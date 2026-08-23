<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi paling kritikal: assertGuruOwnsKonseling() lama
 * meloloskan siswa pemilik konsultasi untuk konfirmasi/laporan/ubah status
 * — padahal tindakan itu seharusnya wewenang Guru BK saja. Sekarang
 * assertGuruCanManageKonseling() TIDAK PERNAH meloloskan siswa, dan Guru A
 * tidak boleh mengelola konsultasi milik Guru B.
 */
class KonselingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_tidak_bisa_konfirmasi_konsultasi_miliknya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson("/api/konseling/{$row->id}/konfirmasi", [])
            ->assertForbidden();

        $this->assertSame('Menunggu', $row->fresh()->status);
    }

    public function test_siswa_tidak_bisa_membuat_laporan_konsultasi_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Proses',
            'status_konfirmasi' => 'Dikonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson("/api/konseling/{$row->id}/laporan", [
            'laporan_kesimpulan' => 'Coba isi sendiri',
        ])->assertForbidden();
    }

    public function test_guru_lain_tidak_bisa_konfirmasi_konsultasi_guru_pemilik(): void
    {
        $siswa = Siswa::factory()->create();
        $guruPemilik = GuruBk::factory()->create();
        $guruLain = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guruPemilik->id,
            'guru_bk' => $guruPemilik->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($guruLain, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/konfirmasi", [])
            ->assertForbidden();

        $this->assertSame('Menunggu', $row->fresh()->status);
    }

    public function test_guru_pemilik_bisa_konfirmasi_konsultasinya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/konfirmasi", [])
            ->assertOk();

        $this->assertSame('Proses', $row->fresh()->status);
    }

    public function test_kepsek_hanya_boleh_melihat_bukan_mengelola_konsultasi(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $kepsek = Kepsek::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($kepsek, ['kepsek']);

        // Boleh lihat (monitoring)
        $this->getJson("/api/konseling/detail/{$row->id}")->assertOk();

        // Tidak boleh ubah status/konfirmasi
        $this->putJson("/api/konseling/{$row->id}/konfirmasi", [])
            ->assertForbidden();
    }
}
