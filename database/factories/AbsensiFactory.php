<?php

namespace Database\Factories;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absensi>
 */
class AbsensiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'kelas_id' => Kelas::factory(),
            'user_id' => User::factory()->guru(),
            'periode_id' => Periode::factory(),
            'tanggal' => fake()->date(),
            'status' => fake()->randomElement(['hadir', 'izin', 'sakit', 'alpa']),
            'keterangan' => null,
        ];
    }
}
