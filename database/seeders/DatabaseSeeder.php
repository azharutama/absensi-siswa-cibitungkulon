<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::create([
            'nama' => 'Operator SD',
            'email' => 'operator@cibitungkulon.test',
            'no_telepon' => '081234567890',
            'role' => 'operator',
            'jenis_kelamin' => 'laki-laki',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'nama' => 'Guru SD',
            'email' => 'guru@cibitungkulon.test',
            'no_telepon' => '081234567891',
            'role' => 'guru',
            'jenis_kelamin' => 'perempuan',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'nama' => 'Kepala Sekolah',
            'email' => 'kepsek@cibitungkulon.test',
            'no_telepon' => '081234567892',
            'role' => 'kepala_sekolah',
            'jenis_kelamin' => 'laki-laki',
            'password' => bcrypt('password'),
        ]);
    }
}
