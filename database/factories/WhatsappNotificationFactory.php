<?php

namespace Database\Factories;

use App\Models\Absensi;
use App\Models\Siswa;
use App\Models\WhatsappNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WhatsappNotification> */
class WhatsappNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'absensi_id' => Absensi::factory(),
            'siswa_id' => Siswa::factory(),
            'parent_name' => fake()->name(),
            'parent_phone' => '62'.fake()->numerify('8##########'),
            'message' => fake()->sentence(),
            'status' => 'pending',
            'provider' => 'fonnte',
            'attempts' => 0,
        ];
    }
}
