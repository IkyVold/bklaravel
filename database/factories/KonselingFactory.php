<?php

namespace Database\Factories;

use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class KonselingFactory extends Factory
{
    protected $model = Konseling::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'guru_id' => null,
            'guru_bk' => $this->faker->name(),
            'tanggal' => now()->addDay()->toDateString(),
            'jam' => '09:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => $this->faker->sentence(),
            'kelas_siswa' => '10 IPA 1',
            'status' => 'Menunggu',
            'status_konfirmasi' => 'Belum Dikonfirmasi',
        ];
    }
}
