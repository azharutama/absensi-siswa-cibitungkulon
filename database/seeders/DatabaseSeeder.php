<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'development', 'production'])) {
            $this->command?->warn('Seeder data demo hanya boleh dijalankan pada environment local/testing/development/production.');

            return;
        }

        DB::transaction(function (): void {
            $this->call([
                GuruSeeder::class,
                PeriodeSeeder::class,
                KelasSeeder::class,
                SiswaSeeder::class,
            ]);

            // Absensi dan rekap sengaja dibiarkan kosong agar diisi oleh guru.
        }, attempts: 3);
    }
}
