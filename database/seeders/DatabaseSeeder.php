<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'nama' => 'Test User',
            'email' => 'test@example.com',
            'no_telepon' => '082123456789',
            'role' => 'operator',
            'jenis_kelamin' => 'laki-laki',
            'password' => bcrypt('password'),
        ]);
    }
}
