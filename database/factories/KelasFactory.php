<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_kelas' => fake()->numberBetween(1, 6).'-'.fake()->randomElement(['A', 'B']),
            'periode_id' => Periode::factory(),
            'status' => 'aktif',
        ];
    }
}
