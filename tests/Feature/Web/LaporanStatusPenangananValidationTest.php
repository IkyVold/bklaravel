<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\StatusPenanganan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup poin revisi 26 Agustus 2026 #6: "Status penanganan laporan
 * masih menerima string bebas". Dulu laporan_status_penanganan hanya
 * divalidasi 'string|max:80', sehingga request manual bisa mengirim
 * nilai seperti 'monitoring' (huruf kecil) atau 'Monitoring ' (ada
 * spasi) dan tetap menyelesaikan laporan tanpa aturan wajib sesi
 * lanjutan (karena KonselingReportService membandingkan persis ===
 * 'Monitoring'). Sekarang wajib salah satu dari StatusPenanganan::ALL.
 */
class LaporanStatusPenangananValidationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsGuru(GuruBk $guru): void
    {
        $this->withSession([
            'auth_role' => 'guru',
            'auth_id' => $guru->id,
            'auth_user' => ['username' => $guru->username, 'nama' => $guru->nama],
            // PERBAIKAN (revisi 26 Agustus 2026, poin 3): baseline
            // password_version wajib disertakan, sama seperti yang
            // dilakukan Web\AuthController@loginStaff saat login
            // sungguhan — kalau tidak, RoleAuth akan menganggap password
            // sudah berubah sejak session ini dibuat dan memaksa logout.
            'auth_password_version' => (int) $guru->password_version,
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

    /**
     * PERBAIKAN: sebelumnya test ini mengharapkan 'Monitoring ' (ada
     * spasi di akhir) ditolak lewat assertSessionHasErrors(), dengan
     * asumsi Rule::in() akan melihat nilai apa adanya dengan spasinya.
     * Nyatanya Laravel 11 memasang middleware global TrimStrings untuk
     * SEMUA request (web maupun API) yang otomatis memangkas spasi di
     * awal/akhir setiap input string SEBELUM validasi dijalankan. Jadi
     * 'Monitoring ' sudah menjadi 'Monitoring' begitu sampai ke
     * Rule::in() — nilai tersebut sah, bukan ditolak. Celah pada catatan
     * revisi (spasi lolos memakai perbandingan string ketat lama) sudah
     * tertutup ganda: TrimStrings menormalkannya menjadi nilai kanonik
     * yang benar, lalu nilai kanonik itu tunduk pada aturan bisnis yang
     * sama seperti 'Monitoring' murni — termasuk wajib mengisi sesi
     * lanjutan. Test ini sekarang memverifikasi perilaku yang
     * sesungguhnya terjadi (dan tetap aman): tanpa tanggal/jam sesi
     * lanjutan, request tetap gagal — bukan lewat error validasi field,
     * melainkan lewat aturan bisnis KonselingReportService, persis sama
     * seperti mengirim 'Monitoring' tanpa spasi sama sekali (lihat
     * test_status_penanganan_monitoring_tetap_wajib_sesi_lanjutan).
     */
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
