<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeder akun demo dilewati di luar environment local/testing.');

            return;
        }

        $password = (string) config('app.seed_default_password');

        if ($password === '') {
            $this->command?->warn('SEED_DEFAULT_PASSWORD belum diisi; akun demo tidak dibuat.');

            return;
        }

        $users = [
            [
                'nama' => 'Operator SD',
                'email' => 'operator@gmail.com',
                'no_telepon' => '081234567890',
                'role' => 'operator',
                'jenis_kelamin' => 'laki-laki',
            ],
            [
                'nama' => 'Guru SD',
                'email' => 'guru@gmail.com',
                'no_telepon' => '081234567891',
                'role' => 'guru',
                'jenis_kelamin' => 'perempuan',
            ],
            [
                'nama' => 'Kepala Sekolah',
                'email' => 'kepsek@gmail.com',
                'no_telepon' => '081234567892',
                'role' => 'kepala_sekolah',
                'jenis_kelamin' => 'laki-laki',
            ],
        ];

        foreach ($users as $attributes) {
            User::updateOrCreate(
                ['email' => $attributes['email']],
                [...$attributes, 'password' => Hash::make($password)]
            );
        }
    }
}
