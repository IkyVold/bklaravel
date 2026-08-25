<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 25 Agustus 2026 #3: "Admin masih dapat membaca
 * seluruh narasi konsultasi". Sebelumnya getDetail()/listAll()/listBySiswa()
 * hanya menyaring field isi konsultasi (deskripsi, laporan_kesimpulan,
 * laporan_rekomendasi, laporan_catatan_tambahan) untuk Kepsek lewat
 * Konseling::untukMonitoringKepsek() — Admin dikecualikan dan tetap
 * menerima $row utuh. Sekarang Admin disaring dengan cara yang sama persis
 * dengan Kepsek di ketiga endpoint tsb. Guru BK pemilik & siswa pemilik
 * tidak terpengaruh — mereka tetap menerima data lengkap.
 */
class KonselingAdminSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonselingDenganLaporan(): array
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'deskripsi' => 'Rahasia: siswa bercerita soal masalah keluarga',
            'status' => 'Selesai',
            'status_konfirmasi' => 'Dikonfirmasi',
            'laporan_kesimpulan' => 'Kesimpulan rahasia sesi konseling',
            'laporan_rekomendasi' => 'Rekomendasi rahasia untuk siswa',
            'laporan_catatan_tambahan' => 'Catatan tambahan rahasia',
        ]);

        return [$row, $siswa, $guru];
    }

    public function test_admin_tidak_menerima_isi_konsultasi_pada_getDetail(): void
    {
        [$row] = $this->buatKonselingDenganLaporan();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson("/api/konseling/detail/{$row->id}")
            ->assertOk();

        $response->assertJsonMissing(['deskripsi' => $row->deskripsi]);
        $response->assertJsonMissingPath('data.deskripsi');
        $response->assertJsonMissingPath('data.laporan_kesimpulan');
        $response->assertJsonMissingPath('data.laporan_rekomendasi');
        $response->assertJsonMissingPath('data.laporan_catatan_tambahan');

        // Data administratif tetap ada untuk keperluan Admin yang sah.
        $response->assertJsonPath('data.id', $row->id);
        $response->assertJsonPath('data.status', 'Selesai');
    }

    public function test_admin_tidak_menerima_isi_konsultasi_pada_listAll(): void
    {
        [$row] = $this->buatKonselingDenganLaporan();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/konseling-all')->assertOk();

        $response->assertJsonMissingPath('data.0.deskripsi');
        $response->assertJsonMissingPath('data.0.laporan_kesimpulan');
        $response->assertJsonPath('data.0.id', $row->id);
    }

    public function test_admin_tidak_menerima_isi_konsultasi_pada_listBySiswa(): void
    {
        [$row, $siswa] = $this->buatKonselingDenganLaporan();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson("/api/konseling/{$siswa->nis}")->assertOk();

        $response->assertJsonMissingPath('data.0.deskripsi');
        $response->assertJsonMissingPath('data.0.laporan_kesimpulan');
        $response->assertJsonPath('data.0.id', $row->id);
    }

    public function test_kepsek_tetap_disaring_sama_seperti_sebelumnya(): void
    {
        // Regression guard: pastikan perubahan poin 3 tidak sengaja
        // melonggarkan aturan Kepsek yang sudah benar sejak revisi
        // sebelumnya.
        [$row] = $this->buatKonselingDenganLaporan();

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $response = $this->getJson("/api/konseling/detail/{$row->id}")->assertOk();
        $response->assertJsonMissingPath('data.deskripsi');
        $response->assertJsonMissingPath('data.laporan_kesimpulan');
    }

    public function test_guru_pemilik_tetap_menerima_data_lengkap(): void
    {
        // Regression guard: sanitasi Admin/Kepsek TIDAK boleh menyentuh
        // hak Guru BK pemilik untuk melihat isi konsultasinya sendiri.
        [$row, , $guru] = $this->buatKonselingDenganLaporan();

        Sanctum::actingAs($guru, ['guru']);

        $response = $this->getJson("/api/konseling/detail/{$row->id}")->assertOk();
        $response->assertJsonPath('data.deskripsi', $row->deskripsi);
        $response->assertJsonPath('data.laporan_kesimpulan', $row->laporan_kesimpulan);
    }

    public function test_siswa_pemilik_tetap_menerima_data_lengkap(): void
    {
        [$row, $siswa] = $this->buatKonselingDenganLaporan();

        Sanctum::actingAs($siswa, ['siswa']);

        $response = $this->getJson("/api/konseling/detail/{$row->id}")->assertOk();
        $response->assertJsonPath('data.deskripsi', $row->deskripsi);
        $response->assertJsonPath('data.laporan_kesimpulan', $row->laporan_kesimpulan);
    }
}
