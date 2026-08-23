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
            'nis' => (string) $this->faker->unique()->numberBetween(1000000000, 9999999999),
            'nama' => $this->faker->name(),
            'kelas' => $this->faker->randomElement(['10 IPA 1', '11 IPS 2', '12 IPA 3']),
            'password' => 'password', // di-hash otomatis lewat setPasswordAttribute()
            'jenis_kelamin' => $this->faker->randomElement(['Laki-laki', 'Perempuan']),
        ];
    }
}
