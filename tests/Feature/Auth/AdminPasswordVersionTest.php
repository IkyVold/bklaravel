<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup revisi 27 Agustus 2026, poin 5: "password_version Admin belum
 * ikut bertambah" — Admin::setPasswordAttribute() sebelumnya HANYA
 * menstempel password_changed_at, tidak ikut menaikkan password_version
 * seperti GuruBk dan Kepsek. Padahal RoleAuth (middleware web) memutus
 * session lama dengan membandingkan password_version akun di database ke
 * baseline yang disimpan session saat login (lihat
 * AccountDeactivationRevokesAccessTest untuk kasus GuruBk yang setara).
 * Tanpa perbaikan ini, begitu password Admin diganti (mis. lewat Tinker,
 * atau saat fitur reset Admin ditambahkan), session Web Admin lama TIDAK
 * akan otomatis diputus.
 */
class AdminPasswordVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mengganti_password_admin_menaikkan_password_version(): void
    {
        $admin = Admin::factory()->create();
        $versionAwal = (int) $admin->password_version;

        $admin->password = 'password-baru-admin';
        $admin->save();

        $this->assertSame(
            $versionAwal + 1,
            (int) $admin->fresh()->password_version,
            'password_version Admin harus naik tepat 1 setiap kali password diganti, sama seperti GuruBk/Kepsek.'
        );
    }

    public function test_password_version_admin_naik_konsisten_dengan_guru_bk_dan_kepsek(): void
    {
        $admin = Admin::factory()->create();
        $guru = GuruBk::factory()->create();
        $kepsek = Kepsek::factory()->create();

        $admin->password = 'password-baru-admin';
        $admin->save();

        $guru->password = 'password-baru-guru';
        $guru->save();

        $kepsek->password = 'password-baru-kepsek';
        $kepsek->save();

        // Ketiga role staff harus punya perilaku IDENTIK: counter naik
        // tepat 1 dari nilai default (1) menjadi 2.
        $this->assertSame(2, (int) $admin->fresh()->password_version);
        $this->assertSame(2, (int) $guru->fresh()->password_version);
        $this->assertSame(2, (int) $kepsek->fresh()->password_version);
    }

    public function test_menyimpan_admin_tanpa_mengganti_password_tidak_menaikkan_password_version(): void
    {
        $admin = Admin::factory()->create();
        $versionAwal = (int) $admin->password_version;

        // Update field lain (bukan password) tidak boleh ikut menaikkan
        // counter — hanya perubahan password yang relevan untuk RoleAuth.
        $admin->nama = 'Nama Admin Baru';
        $admin->save();

        $this->assertSame($versionAwal, (int) $admin->fresh()->password_version);
    }
}
