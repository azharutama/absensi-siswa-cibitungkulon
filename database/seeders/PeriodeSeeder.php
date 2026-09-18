<?php

namespace Database\Seeders;

use App\Models\Periode;
use Illuminate\Database\Seeder;

class PeriodeSeeder extends Seeder
{
    /** @var array<int, array<string, bool|string>> */
    public const PERIODS = [
        [
            'tahun_ajaran' => '2026/2027',
            'semester' => 1,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Ganjil 2026/2027',
            'tanggal_mulai' => '2026-08-1',
            'tanggal_selesai' => '2026-12-20',
        ],
        [
            'tahun_ajaran' => '2026/2027',
            'semester' => 2,
            'tipe_periode' => 'semester',
            'nama_periode' => 'Semester Genap 2026/2027',
            'tanggal_mulai' => '2027-01-1',
            'tanggal_selesai' => '2027-03-20',
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
