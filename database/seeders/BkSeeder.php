<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder DINONAKTIFKAN agar tidak menimpa data siswa/guru asli.
 * Akun demo hanya dibuat manual jika benar-benar dibutuhkan.
 */
class BkSeeder extends Seeder
{
    public function run(): void
    {
        // Sengaja kosong — data memakai database bk_system yang sudah ada.
        $this->command?->info('BkSeeder dilewati: data memakai database yang sudah ada.');
    }
}
