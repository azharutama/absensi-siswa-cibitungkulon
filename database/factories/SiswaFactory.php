<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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
            'periode_id' => Periode::factory(),
            'status' => 'aktif',
        ];
    }
}
