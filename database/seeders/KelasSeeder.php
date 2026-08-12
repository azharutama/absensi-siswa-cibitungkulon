<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class KelasSeeder extends Seeder
{
    public const CLASS_NAMES = [
        '1-A', '1-B',
        '2-A', '2-B',
        '3-A', '3-B',
        '4-A', '4-B',
        '5-A', '5-B',
        '6-A', '6-B',
    ];

    public function run(): void
    {
        $teachers = User::query()
            ->where('role', 'guru')
            ->whereIn('nip', array_map(
                fn (int $number): string => GuruSeeder::nipFor($number),
                range(1, GuruSeeder::TOTAL_GURU),
            ))
            ->get()
            ->keyBy('nip');

        if ($teachers->count() !== GuruSeeder::TOTAL_GURU) {
            throw new RuntimeException('Seeder kelas memerlukan '.GuruSeeder::TOTAL_GURU.' akun guru.');
        }

        foreach (self::CLASS_NAMES as $classIndex => $className) {
            $kelas = Kelas::query()->updateOrCreate(
                [
                    'nama_kelas' => $className,
                ],
                [
                    'status' => 'aktif',
                ],
            );

            $teacherNumber = $classIndex + 1;
            $teacher = $teachers->get(GuruSeeder::nipFor($teacherNumber));

            if (! $teacher) {
                throw new RuntimeException('Guru untuk kelas demo tidak ditemukan.');
            }

            $kelas->update(['guru_id' => $teacher->id]);
        }
    }
}
