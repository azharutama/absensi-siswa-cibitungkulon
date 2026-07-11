<?php

namespace Database\Factories;

use App\Models\HariLibur;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HariLibur> */
class HariLiburFactory extends Factory
{
    public function definition(): array
    {
        return [
            'periode_id' => Periode::factory(),
            'tipe' => 'nasional',
            'hari' => null,
            'tanggal' => fake()->date(),
            'keterangan' => fake()->words(3, true),
        ];
    }
}
