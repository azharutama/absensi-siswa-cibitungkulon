<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\SiswaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_data_does_not_include_attendance_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('siswas', SiswaSeeder::TOTAL_SISWA);
        $this->assertDatabaseCount('users', GuruSeeder::TOTAL_GURU + 2);
        $this->assertDatabaseCount('absensis', 0);
        $this->assertDatabaseCount('rekap_absensis', 0);
        $this->assertDatabaseCount('whatsapp_notifications', 0);
    }
}
