<?php

namespace Database\Factories;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu
            // numberBetween(1000000000, 9999999999) — 10 digit, tidak lagi
            // sesuai kolom nis varchar(4) & aturan NIS lokal sekolah
            // (tepat 4 digit angka).
            'nis' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'nama' => $this->faker->name(),
            'kelas' => $this->faker->randomElement(['10 IPA 1', '11 IPS 2', '12 IPA 3']),
            'password' => 'password', // di-hash otomatis lewat setPasswordAttribute()
            'jenis_kelamin' => $this->faker->randomElement(['Laki-laki', 'Perempuan']),
        ];
    }
}
