<?php

namespace Database\Seeders;

use App\Models\Periode;
use App\Models\Siswa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AbsensiSeeder extends Seeder
{
    private const MAX_SCHOOL_DAYS_PER_PERIOD = 12;

    public function run(): void
    {
        $activePeriod = Periode::query()
            ->whereDate('tanggal_mulai', '<=', today())
            ->orderByDesc('tanggal_mulai')
            ->first();
        $students = Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->whereBetween('nis', [SiswaSeeder::FIRST_NIS, SiswaSeeder::LAST_NIS])
            ->orderBy('nis')
            ->get();
        $waliByClass = DB::table('kelas_user')
            ->where('is_wali_kelas', true)
            ->pluck('user_id', 'kelas_id');

        if (! $activePeriod || $students->count() !== SiswaSeeder::TOTAL_SISWA) {
            throw new RuntimeException('Seeder absensi memerlukan satu periode aktif dan '.SiswaSeeder::TOTAL_SISWA.' siswa.');
        }

        $rows = [];
        $now = now();
        $dates = $this->schoolDates($activePeriod);

        if ($dates === []) {
            throw new RuntimeException('Periode aktif belum memiliki tanggal hari sekolah yang dapat diisi.');
        }

        foreach ($students as $studentIndex => $student) {
            $class = $student->kelas;
            $teacherId = $class ? $waliByClass->get($class->id) : null;

            if (! $class || ! $teacherId) {
                throw new RuntimeException('Kelas atau wali kelas siswa untuk data absensi tidak valid.');
            }

            foreach ($dates as $dateIndex => $date) {
                $status = $this->attendanceStatus($studentIndex, $dateIndex);

                $rows[] = [
                    'siswa_id' => $student->id,
                    'kelas_id' => $class->id,
                    'user_id' => $teacherId,
                    'periode_id' => $activePeriod->id,
                    'tanggal' => $date->toDateString(),
                    'status' => $status,
                    'keterangan' => $this->attendanceNote($status),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 1000) {
                    $this->saveRows($rows);
                    $rows = [];
                }
            }
        }

        $this->saveRows($rows);
    }

    /** @return array<int, CarbonImmutable> */
    private function schoolDates(Periode $period): array
    {
        $date = CarbonImmutable::parse($period->tanggal_mulai);
        $lastDate = CarbonImmutable::parse($period->tanggal_selesai)
            ->min(CarbonImmutable::today());
        $dates = [];

        while ($date->lessThanOrEqualTo($lastDate) && count($dates) < self::MAX_SCHOOL_DAYS_PER_PERIOD) {
            if (! $date->isWeekend()) {
                $dates[] = $date;
            }

            $date = $date->addDay();
        }

        return $dates;
    }

    private function attendanceStatus(int $studentIndex, int $dateIndex): string
    {
        $score = (($studentIndex * 17) + ($dateIndex * 13)) % 100;

        return match (true) {
            $score <= 2 => 'alpa',
            $score <= 6 => 'sakit',
            $score <= 9 => 'izin',
            default => 'hadir',
        };
    }

    private function attendanceNote(string $status): ?string
    {
        return match ($status) {
            'alpa' => 'Tanpa keterangan',
            'sakit' => 'Sakit',
            'izin' => 'Keperluan keluarga',
            default => null,
        };
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function saveRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('absensis')->upsert(
            $rows,
            ['siswa_id', 'tanggal'],
            ['kelas_id', 'user_id', 'periode_id', 'status', 'keterangan', 'updated_at'],
        );
    }
}
