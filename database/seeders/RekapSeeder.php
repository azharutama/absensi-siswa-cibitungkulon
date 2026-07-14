<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RekapSeeder extends Seeder
{
    public function run(): void
    {
        // Halaman Rekap menghitung data langsung dari absensis.
        $this->call(AbsensiSeeder::class);
    }
}
