<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('#####'),
            'nisn' => fake()->unique()->numerify('##########'),
            'nama_siswa' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['laki-laki', 'perempuan']),
            'nama_ayah' => fake()->name('male'),
            'no_whatsapp_ayah' => '08'.fake()->numerify('##########'),
            'nama_ibu' => fake()->name('female'),
            'no_whatsapp_ibu' => '08'.fake()->numerify('##########'),
            'nama_wali' => null,
            'no_whatsapp_wali' => null,
            'kelas_id' => Kelas::factory(),
        ];
    }
}
