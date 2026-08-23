<?php

namespace Database\Factories;

use App\Models\GuruBk;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuruBkFactory extends Factory
{
    protected $model = GuruBk::class;

    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => 'password',
            'nama' => $this->faker->name(),
            'spesialisasi' => 'Guru BK',
            'is_active' => true,
        ];
    }
}
