<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 25 Agustus 2026, poin 9): Web/KonselingController@konfirmasi
 * dulu memvalidasi status_konfirmasi dengan 'nullable|string|max:30' —
 * nilai bebas apa pun bisa dikirim client, sementara $row->status tetap
 * dipaksa 'Proses' tanpa syarat. Kalau field dimanipulasi (mis. dikirim
 * "Ditolak"), bisa terbentuk state ganjil status=Proses &
 * status_konfirmasi=Ditolak. Sekarang nilainya dikunci ke satu-satunya
 * pilihan yang sah untuk form ini: 'Terkonfirmasi'.
 */
class KonfirmasiStatusValidationTest extends TestCase
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

    public function test_status_konfirmasi_nilai_bebas_ditolak(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.konfirmasi', $row->id), [
            'tanggal_konfirmasi' => now()->addDays(3)->toDateString(),
            'jam_konfirmasi' => '10:00',
            'status_konfirmasi' => 'Ditolak',
        ])->assertSessionHasErrors('status_konfirmasi');

        // Tidak ada state ganjil yang terbentuk — baris tetap Menunggu dan
        // status_konfirmasi tidak berubah dari default awal.
        $fresh = $row->fresh();
        $this->assertSame('Menunggu', $fresh->status);
        $this->assertSame($row->status_konfirmasi, $fresh->status_konfirmasi);
        $this->assertNotSame('Ditolak', $fresh->status_konfirmasi);
    }

    public function test_status_konfirmasi_terkonfirmasi_diterima(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.konfirmasi', $row->id), [
            'tanggal_konfirmasi' => now()->addDays(3)->toDateString(),
            'jam_konfirmasi' => '10:00',
            'status_konfirmasi' => 'Terkonfirmasi',
        ])->assertRedirect();

        $fresh = $row->fresh();
        $this->assertSame('Proses', $fresh->status);
        $this->assertSame('Terkonfirmasi', $fresh->status_konfirmasi);
    }

    public function test_status_konfirmasi_kosong_tetap_default_terkonfirmasi(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        $this->loginAsGuru($guru);

        $this->post(route('guru.konseling.konfirmasi', $row->id), [
            'tanggal_konfirmasi' => now()->addDays(3)->toDateString(),
            'jam_konfirmasi' => '10:00',
        ])->assertRedirect();

        $fresh = $row->fresh();
        $this->assertSame('Proses', $fresh->status);
        $this->assertSame('Terkonfirmasi', $fresh->status_konfirmasi);
    }
}
