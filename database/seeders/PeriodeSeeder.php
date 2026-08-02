<?php

namespace Database\Seeders;

use App\Models\Periode;
use Illuminate\Database\Seeder;

class PeriodeSeeder extends Seeder
{
    /** @var array<int, array<string, bool|string>> */
    public const PERIODS = [
        [
            'tahun_ajaran' => '2025/2026',
            'semester' => 1,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Ganjil 2025/2026',
            'tanggal_mulai' => '2025-07-14',
            'tanggal_selesai' => '2025-12-19',
        ],
        [
            'tahun_ajaran' => '2025/2026',
            'semester' => 2,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Genap 2025/2026',
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-06-26',
        ],
        [
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Ganjil 2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2026-12-18',
        ],
        [
            'tahun_ajaran' => '2026/2027',
            'semester' => 2,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Genap 2026/2027',
            'tanggal_mulai' => '2027-01-01',
            'tanggal_selesai' => '2027-06-30',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERIODS as $attributes) {
            Periode::query()->updateOrCreate(
                ['nama_periode' => $attributes['nama_periode']],
                $attributes,
            );
        }
    }
}