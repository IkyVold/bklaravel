<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\StatusPenanganan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanStatusPenangananValidationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsGuru(GuruBk $guru): void
    {
        $this->withSession([
            'auth_role' => 'guru',
            'auth_id' => $guru->id,
            'auth_user' => ['username' => $guru->username, 'nama' => $guru->nama],
        ]);
    }

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
        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.laporan', $row->id), [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => 'monitoring', // huruf kecil — bukan nilai master
        ])->assertSessionHasErrors('laporan_status_penanganan');

        $this->assertDatabaseHas('konseling', ['id' => $row->id, 'status' => 'Proses']);
    }

    public function test_status_penanganan_dengan_spasi_ekstra_dinormalisasi_dan_tetap_wajib_sesi_lanjutan(): void
    {
        $guru = GuruBk::factory()->create();
        $row = $this->konfirmasiKonseling($guru);
        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.laporan', $row->id), [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => 'Monitoring ',
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('konseling', ['id' => $row->id, 'status' => 'Proses']);
    }

    public function test_status_penanganan_master_diterima(): void
    {
        $guru = GuruBk::factory()->create();
        $row = $this->konfirmasiKonseling($guru);
        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.laporan', $row->id), [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => StatusPenanganan::SELESAI_TERATASI,
        ])->assertRedirect();

        $this->assertDatabaseHas('konseling', [
            'id' => $row->id,
            'status' => 'Selesai',
            'laporan_status_penanganan' => StatusPenanganan::SELESAI_TERATASI,
        ]);
    }

    public function test_status_penanganan_monitoring_tetap_wajib_sesi_lanjutan(): void
    {
        $guru = GuruBk::factory()->create();
        $row = $this->konfirmasiKonseling($guru);
        $this->loginAsGuru($guru);

        // Monitoring tanpa tanggal/jam sesi lanjutan — tetap harus gagal
        // di level business rule KonselingReportService (bukan error
        // validasi format), memastikan Rule::in() tidak menggantikan
        // aturan follow-up yang sudah ada.
        $this->post(route('guru.konseling.laporan', $row->id), [
            'laporan_kesimpulan' => 'Kesimpulan konseling.',
            'laporan_rekomendasi' => 'Rekomendasi tindak lanjut.',
            'laporan_status_penanganan' => StatusPenanganan::MONITORING,
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('konseling', ['id' => $row->id, 'status' => 'Proses']);
    }
}
