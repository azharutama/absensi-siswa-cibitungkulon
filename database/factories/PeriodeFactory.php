<?php

namespace Database\Factories;

use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periode>
 */
class PeriodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', '+6 months');
        $end = (clone $start)->modify('+6 months');

        return [
            'nama_periode' => sprintf(
                'Semester %s %s/%s',
                fake()->randomElement(['Ganjil', 'Genap']),
                $start->format('Y'),
                $end->format('Y')
            ),
            'tanggal_mulai' => $start->format('Y-m-d'),
            'tanggal_selesai' => $end->format('Y-m-d'),
            'status_aktif' => false,
        ];
    }
}
