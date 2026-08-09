<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Seeder;
use RuntimeException;

class SiswaSeeder extends Seeder
{
    public const TOTAL_SISWA = 30;

    public const PARENT_WHATSAPP = '081398431964';

    public const MOTHER_WHATSAPP = '085697265714';

    public const FIRST_NIS = '20260001';

    public const LAST_NIS = '20260030';

    public function run(): void
    {
        $class = Kelas::query()
            ->where('nama_kelas', '1-A')
            ->first();

        if (! $class) {
            throw new RuntimeException('Seeder siswa memerlukan kelas 1-A yang sudah di-seed.');
        }

        $faker = fake('id_ID');
        $faker->seed(20260712);
        $now = now();
        $rows = [];

        for ($number = 1; $number <= self::TOTAL_SISWA; $number++) {
            $isFemale = $number % 2 === 0;

            $rows[] = [
                'nis' => sprintf('2026%04d', $number),
                'nisn' => sprintf('0099%06d', $number),
                'nama_siswa' => $faker->name($isFemale ? 'female' : 'male'),
                'jenis_kelamin' => $isFemale ? 'perempuan' : 'laki-laki',
                'alamat' => $faker->address(),
                'nama_ayah' => $faker->name('male'),
                'no_whatsapp_ayah' => self::PARENT_WHATSAPP,
                'nama_ibu' => $faker->name('female'),
                'no_whatsapp_ibu' => self::MOTHER_WHATSAPP,
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
                    'kelas_id',
                    'updated_at',
                ],
            );
        }
    }
}
