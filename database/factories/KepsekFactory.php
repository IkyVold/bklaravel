<?php

namespace Database\Factories;

use App\Models\Kepsek;
use Illuminate\Database\Eloquent\Factories\Factory;

class KepsekFactory extends Factory
{
    protected $model = Kepsek::class;

    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'password' => 'password',
            'nama' => $this->faker->name(),
            'is_active' => true,
        ];
    }
}
