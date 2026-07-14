<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GuruSeeder extends Seeder
{
    public const TOTAL_GURU = 40;

    public function run(): void
    {
        $password = Hash::make('password');

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
            $this->saveUser([
                'nama' => "Guru {$number}",
                'username' => "guru{$number}",
                'nip' => sprintf('GURU%03d', $number),
                'email' => "guru{$number}@gmail.com",
                'no_telepon' => sprintf('08121%07d', $number),
                'role' => 'guru',
                'jenis_kelamin' => $number % 2 === 0 ? 'perempuan' : 'laki-laki',
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
