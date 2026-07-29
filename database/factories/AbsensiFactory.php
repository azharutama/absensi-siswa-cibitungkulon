<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsensiFactory extends Factory
{
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
