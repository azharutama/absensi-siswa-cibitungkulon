<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_telepon' => fake()->unique()->phoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['operator', 'guru', 'kepala_sekolah']),
            'jenis_kelamin' => fake()->randomElement(['laki-laki', 'perempuan']),

        ];
    }

    public function operator(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'operator',
        ]);
    }

    public function guru(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'guru',
        ]);
    }

    public function kepalaSekolah(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'kepala_sekolah',
        ]);
    }
}
