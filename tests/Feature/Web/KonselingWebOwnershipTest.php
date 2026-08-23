<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menutup dua poin revisi jalur web:
 *  1) show() dulu pakai findOrFail() generik untuk role guru, sehingga
 *     Guru A bisa lihat detail konsultasi Guru B hanya dengan ganti ID di
 *     URL. Sekarang wajib lewat findGuruKonseling() yang di-scope guru_id.
 *  2) destroySiswa() dulu hard-delete baris konsultasi. Sekarang diganti
 *     soft-cancel (status=Dibatalkan) dan route DELETE sudah dihapus.
 */
class KonselingWebOwnershipTest extends TestCase
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

    private function loginAsSiswa(Siswa $siswa): void
    {
        $this->withSession([
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'kelas' => $siswa->kelas],
        ]);
    }

    public function test_guru_lain_tidak_bisa_lihat_detail_konsultasi_guru_pemilik(): void
    {
        $siswa = Siswa::factory()->create();
        $guruPemilik = GuruBk::factory()->create();
        $guruLain = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guruPemilik->id,
            'guru_bk' => $guruPemilik->nama,
        ]);

        $this->loginAsGuru($guruLain);

        $response = $this->get(route('guru.konseling.show', $row->id));

        // findGuruKonseling() men-scope ke guru_id milik sesi login —
        // Guru B mengganti ID di URL harus gagal (404/403), bukan 200.
        $response->assertStatus(404);
    }

    public function test_guru_pemilik_bisa_lihat_detail_konsultasinya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
        ]);

        $this->loginAsGuru($guru);

        $this->get(route('guru.konseling.show', $row->id))->assertOk();
    }

    public function test_route_hard_delete_konsultasi_sudah_tidak_ada(): void
    {
        $this->assertFalse(
            Route::has('siswa.konseling.destroy'),
            'Route hard-delete konsultasi seharusnya sudah dihapus sepenuhnya.'
        );
    }

    public function test_batal_siswa_soft_cancel_bukan_hard_delete(): void
    {
        $siswa = Siswa::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'status' => 'Menunggu',
        ]);

        $this->loginAsSiswa($siswa);

        $this->post(route('siswa.konseling.batal', $row->id), [
            'alasan' => 'Sudah tidak jadi konsultasi minggu ini',
        ])->assertRedirect();

        // Baris TIDAK boleh hilang dari database (audit trail) — hanya
        // statusnya berubah jadi Dibatalkan.
        $this->assertDatabaseHas('konseling', [
            'id' => $row->id,
            'status' => 'Dibatalkan',
        ]);
    }
}
