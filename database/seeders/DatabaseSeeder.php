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
        $password = Hash::make('password');

        $users = [
            [
                'nama' => 'Operator SD',
                'username' => 'operator',
                'nip' => 'OPERATOR001',
                'email' => 'operator@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000001',
                'role' => 'operator',
                'jenis_kelamin' => 'laki-laki',
            ],
            [
                'nama' => 'Guru 1',
                'username' => 'guru1',
                'nip' => 'GURU001',
                'email' => 'guru1@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000002',
                'role' => 'guru',
                'jenis_kelamin' => 'laki-laki',
            ],
            [
                'nama' => 'Guru 2',
                'username' => 'guru2',
                'nip' => 'GURU002',
                'email' => 'guru2@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000003',
                'role' => 'guru',
                'jenis_kelamin' => 'perempuan',
            ],
            [
                'nama' => 'Guru 3',
                'username' => 'guru3',
                'nip' => 'GURU003',
                'email' => 'guru3@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000004',
                'role' => 'guru',
                'jenis_kelamin' => 'laki-laki',
            ],
            [
                'nama' => 'Guru 4',
                'username' => 'guru4',
                'nip' => 'GURU004',
                'email' => 'guru4@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000005',
                'role' => 'guru',
                'jenis_kelamin' => 'perempuan',
            ],
            [
                'nama' => 'Guru 5',
                'username' => 'guru5',
                'nip' => 'GURU005',
                'email' => 'guru5@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000006',
                'role' => 'guru',
                'jenis_kelamin' => 'laki-laki',
            ],
            [
                'nama' => 'Kepala Sekolah',
                'username' => 'kepala_sekolah',
                'nip' => 'KEPSEK001',
                'email' => 'kepala.sekolah@sdncibitungkulon02.sch.id',
                'no_telepon' => '081200000007',
                'role' => 'kepala_sekolah',
                'jenis_kelamin' => 'laki-laki',
            ],
        ];

        foreach ($users as $attributes) {
            User::query()->updateOrCreate(
                ['username' => $attributes['username']],
                [...$attributes, 'password' => $password],
            );
        }
    }
}
