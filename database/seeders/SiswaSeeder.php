<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Seeder;
use RuntimeException;

class SiswaSeeder extends Seeder
{
    public const TOTAL_SISWA = 400;

    public const PARENT_WHATSAPP = '081398431964';

    public const FIRST_NIS = '20260001';

    public const LAST_NIS = '20260400';

    public function run(): void
    {
        $classes = Kelas::query()
            ->whereIn('nama_kelas', KelasSeeder::CLASS_NAMES)
            ->orderBy('nama_kelas')
            ->get();

        if ($classes->count() !== count(KelasSeeder::CLASS_NAMES)) {
            throw new RuntimeException('Seeder siswa memerlukan 12 kelas yang sudah di-seed.');
        }

        $faker = fake('id_ID');
        $faker->seed(20260712);
        $now = now();
        $rows = [];

        for ($number = 1; $number <= self::TOTAL_SISWA; $number++) {
            $isFemale = $number % 2 === 0;
            $class = $classes[($number - 1) % $classes->count()];
            $hasGuardian = $number % 10 === 0;

            $rows[] = [
                'nis' => sprintf('2026%04d', $number),
                'nisn' => sprintf('0099%06d', $number),
                'nama_siswa' => $faker->name($isFemale ? 'female' : 'male'),
                'jenis_kelamin' => $isFemale ? 'perempuan' : 'laki-laki',
                'alamat' => $faker->address(),
                'nama_ayah' => $faker->name('male'),
                'no_whatsapp_ayah' => self::PARENT_WHATSAPP,
                'nama_ibu' => $faker->name('female'),
                'no_whatsapp_ibu' => self::PARENT_WHATSAPP,
                'nama_wali' => $hasGuardian ? $faker->name() : null,
                'no_whatsapp_wali' => $hasGuardian ? self::PARENT_WHATSAPP : null,
                'kelas_id' => $class->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Siswa::query()->upsert(
                $chunk,
                ['nis'],
                [
                    'nisn',
                    'nama_siswa',
                    'jenis_kelamin',
                    'alamat',
                    'nama_ayah',
                    'no_whatsapp_ayah',
                    'nama_ibu',
                    'no_whatsapp_ibu',
                    'nama_wali',
                    'no_whatsapp_wali',
                    'kelas_id',
                    'updated_at',
                ],
            );
        }
    }
}
