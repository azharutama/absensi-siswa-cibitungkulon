<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodeFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', '+6 months');
        $end = (clone $start)->modify('+6 months');
        $tahunAjaran = $start->format('Y').'/'.($start->copy()->addYear()->format('y'));
        $semester = fake()->randomElement([1, 2]);

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester '.($semester === 1 ? 'Ganjil' : 'Genap')." {$tahunAjaran}",
            'tanggal_mulai' => $start->format('Y-m-d'),
            'tanggal_selesai' => $end->format('Y-m-d'),
        ];
    }
}
