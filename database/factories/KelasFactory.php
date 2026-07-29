<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KelasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_kelas' => fake()->numberBetween(1, 6).'-'.fake()->randomElement(['A', 'B']),
            'status' => 'aktif',
        ];
    }
}
