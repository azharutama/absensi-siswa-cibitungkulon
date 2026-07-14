<?php

namespace Database\Seeders;

use App\Models\Periode;
use Illuminate\Database\Seeder;

class PeriodeSeeder extends Seeder
{
    /** @var array<int, array<string, bool|string>> */
    public const PERIODS = [
        [
            'nama_periode' => 'Semester Ganjil 2025/2026',
            'tanggal_mulai' => '2025-07-14',
            'tanggal_selesai' => '2025-12-19',
            'status_aktif' => false,
        ],
        [
            'nama_periode' => 'Semester Genap 2025/2026',
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-06-26',
            'status_aktif' => false,
        ],
        [
            'nama_periode' => 'Semester Ganjil 2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-18',
            'status_aktif' => true,
        ],
    ];

    public function run(): void
    {
        Periode::query()
            ->where('status_aktif', true)
            ->update(['status_aktif' => false]);

        foreach (self::PERIODS as $attributes) {
            Periode::query()->updateOrCreate(
                ['nama_periode' => $attributes['nama_periode']],
                $attributes,
            );
        }
    }
}
