<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonfirmasiStatusValidationTest extends TestCase
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
