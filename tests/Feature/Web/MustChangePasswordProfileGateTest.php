<?php

namespace Tests\Feature\Web;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup revisi 27 Agustus 2026, poin 2 (lanjutan — hasil review dosen
 * penguji), sisi web. Rute 'siswa.profil.update' sengaja dikecualikan
 * dari redirect wajib-ganti-password di RoleAuth (lihat $exemptRoutes di
 * sana) supaya siswa punya jalan untuk mematuhi kewajiban itu. Sebelum
 * perbaikan ini, method update() itu sendiri tidak membedakan permintaan
 * ganti password dari permintaan lain, sehingga session yang sudah
 * dibajak sebelum Admin mereset password tetap bisa mengubah data lain
 * (jenis_kelamin, alamat, no_telepon, foto) lewat rute yang sama tanpa
 * pernah benar-benar mengganti password.
 */
class MustChangePasswordProfileGateTest extends TestCase
{
    use RefreshDatabase;

    private function sessionUntukSiswa(Siswa $siswa): array
    {
        return [
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'must_change_password' => (bool) $siswa->must_change_password],
        ];
    }

    public function test_siswa_wajib_ganti_password_ditolak_mengubah_field_lain_lewat_edit_field(): void
    {
        $siswa = Siswa::factory()->create(['alamat' => 'Alamat Lama', 'must_change_password' => true]);

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'alamat',
                'edit_value' => 'Alamat Baru Dari Attacker',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('Alamat Lama', $siswa->fresh()->alamat);
    }

    public function test_siswa_wajib_ganti_password_ditolak_upload_foto(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true, 'foto_profile' => null]);

        // PERBAIKAN: fake()->image() memerlukan ekstensi GD PHP untuk
        // benar-benar men-generate isi gambar (lihat
        // Illuminate\Http\Testing\FileFactory::generateImage()), yang
        // belum tentu terinstal di semua environment (mis. XAMPP/Laragon
        // default di Windows tanpa GD diaktifkan). fake()->create()
        // dengan mimeType eksplisit menghasilkan UploadedFile palsu yang
        // valid untuk validasi 'image|mimes:...' TANPA perlu GD, karena
        // tidak perlu benar-benar merender pixel gambar apa pun.
        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'foto' => \Illuminate\Http\UploadedFile::fake()->create('foto.jpg', 10, 'image/jpeg'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($siswa->fresh()->foto_profile);
    }

    public function test_siswa_wajib_ganti_password_tetap_bisa_ganti_password_lewat_edit_field(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password_lama', 'must_change_password' => true]);

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'password',
                'current_password' => 'password_lama',
                'edit_value' => 'password_baru_web',
                'edit_value_confirmation' => 'password_baru_web',
            ]);

        $response->assertRedirect();
        $siswa->refresh();
        $this->assertTrue($siswa->verifyPassword('password_baru_web'));
        $this->assertFalse((bool) $siswa->must_change_password);
    }

    public function test_field_lain_yang_diselipkan_bersama_password_lewat_fallback_form_diabaikan(): void
    {
        $siswa = Siswa::factory()->create([
            'password' => 'password_lama',
            'alamat' => 'Alamat Lama',
            'must_change_password' => true,
        ]);

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'current_password' => 'password_lama',
                'password' => 'password_baru_fallback',
                'password_confirmation' => 'password_baru_fallback',
                'alamat' => 'Alamat Yang Diselipkan',
            ]);

        $response->assertRedirect();
        $siswa->refresh();
        $this->assertTrue($siswa->verifyPassword('password_baru_fallback'));
        $this->assertSame('Alamat Lama', $siswa->alamat);
        $this->assertFalse((bool) $siswa->must_change_password);
    }

    public function test_siswa_yang_sudah_tidak_wajib_ganti_password_tetap_bisa_ubah_field_lain(): void
    {
        $siswa = Siswa::factory()->create(['alamat' => 'Alamat Lama', 'must_change_password' => false]);

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'alamat',
                'edit_value' => 'Alamat Baru Yang Sah',
            ]);

        $response->assertRedirect();
        $this->assertSame('Alamat Baru Yang Sah', $siswa->fresh()->alamat);
    }
}
