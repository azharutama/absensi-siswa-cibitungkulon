<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GuruSeeder extends Seeder
{
    public const TOTAL_GURU = 40;

    public const DEFAULT_PASSWORD = 'password';

    public const PRIMARY_TEACHER_EMAIL = 'azhar@gmail.com';

    public const PRIMARY_TEACHER_NAME = 'Muhammad Azhar Utama';

    public function run(): void
    {
        $password = Hash::make(self::DEFAULT_PASSWORD);
        $faker = fake('id_ID');
        $faker->seed(20260714);

        $this->saveUser([
            'nama' => 'Operator SD',
            'username' => 'operator',
            'nip' => 'OPERATOR001',
            'email' => 'operator@gmail.com',
            'no_telepon' => '081200000001',
            'role' => 'operator',
            'jenis_kelamin' => 'laki-laki',
            'password' => $password,
        ]);

        for ($number = 1; $number <= self::TOTAL_GURU; $number++) {
            $isFemale = $number % 2 === 0;

            $this->saveUser([
                'nama' => $number === 1
                    ? self::PRIMARY_TEACHER_NAME
                    : $faker->unique()->name($isFemale ? 'female' : 'male'),
                'username' => "guru{$number}",
                'nip' => sprintf('GURU%03d', $number),
                'email' => $number === 1
                    ? self::PRIMARY_TEACHER_EMAIL
                    : "guru{$number}@gmail.com",
                'no_telepon' => sprintf('08121%07d', $number),
                'role' => 'guru',
                'jenis_kelamin' => $isFemale ? 'perempuan' : 'laki-laki',
                'password' => $password,
            ]);
        }

        $this->saveUser([
            'nama' => 'Kepala Sekolah',
            'username' => 'kepala_sekolah',
            'nip' => 'KEPSEK001',
            'email' => 'kepala.sekolah@gmail.com',
            'no_telepon' => '081299999999',
            'role' => 'kepala_sekolah',
            'jenis_kelamin' => 'laki-laki',
            'password' => $password,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function saveUser(array $attributes): void
    {
        User::query()->updateOrCreate(
            ['username' => $attributes['username']],
            $attributes,
        );
    }
}
