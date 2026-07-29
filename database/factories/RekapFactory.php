<?php

namespace Database\Factories;

use App\Models\Absensi;
use App\Models\Rekap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rekap>
 */
class RekapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'absensi_id' => Absensi::factory(),
            'user_id' => User::factory()->operator(),
            'nomor_bulan' => fake()->date('Y-m'),
            'id_pengun' => null,
            'status_pengiriman' => 'pending',
            'waktu_kirim' => null,
        ];
    }
}
