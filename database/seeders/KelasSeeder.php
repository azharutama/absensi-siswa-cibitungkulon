<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Periode;
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
        $activePeriod = Periode::query()
            ->where('status_aktif', true)
            ->first();

        $teachers = User::query()
            ->where('role', 'guru')
            ->whereIn('nip', array_map(
                fn (int $number): string => sprintf('GURU%03d', $number),
                range(1, GuruSeeder::TOTAL_GURU),
            ))
            ->get()
            ->keyBy('nip');

        if (! $activePeriod || $teachers->count() !== GuruSeeder::TOTAL_GURU) {
            throw new RuntimeException('Seeder kelas memerlukan satu periode aktif dan 40 akun guru.');
        }

        foreach (self::CLASS_NAMES as $classIndex => $className) {
            $kelas = Kelas::query()->updateOrCreate(
                [
                    'periode_id' => $activePeriod->id,
                    'nama_kelas' => $className,
                ],
                [
                    'status' => 'aktif',
                ],
            );

            $teacherAssignments = [];

            for (
                $teacherNumber = $classIndex + 1;
                $teacherNumber <= GuruSeeder::TOTAL_GURU;
                $teacherNumber += count(self::CLASS_NAMES)
            ) {
                $teacher = $teachers->get(sprintf('GURU%03d', $teacherNumber));

                if (! $teacher) {
                    throw new RuntimeException('Guru untuk kelas demo tidak ditemukan.');
                }

                $teacherAssignments[$teacher->id] = [
                    'is_wali_kelas' => $teacherNumber === $classIndex + 1,
                ];
            }

            $kelas->gurus()->sync($teacherAssignments);
        }
    }
}
