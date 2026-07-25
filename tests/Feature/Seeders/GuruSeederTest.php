<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\GuruSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_forty_fictitious_teachers_and_the_primary_teacher_account(): void
    {
        $this->seed(GuruSeeder::class);

        $primaryTeacher = User::query()
            ->where('nip', 'GURU001')
            ->firstOrFail();

        $this->assertSame(GuruSeeder::PRIMARY_TEACHER_NAME, $primaryTeacher->nama);
        $this->assertSame(GuruSeeder::PRIMARY_TEACHER_EMAIL, $primaryTeacher->email);
        $this->assertSame('guru', $primaryTeacher->role);
        $this->assertTrue(Hash::check(GuruSeeder::DEFAULT_PASSWORD, $primaryTeacher->password));

        $teachers = User::query()
            ->where('role', 'guru')
            ->get();

        $this->assertCount(GuruSeeder::TOTAL_GURU, $teachers);
        $this->assertFalse($teachers->contains(
            fn (User $teacher): bool => preg_match('/^Guru \d+$/', $teacher->nama) === 1,
        ));
    }
}
