<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Kirim Tautan');
    }

    public function test_reset_link_can_be_requested_by_whatsapp(): void
    {
        $user = User::factory()->create(['no_telepon' => '081200000001']);

        $this->mock(FonnteService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with($user->no_telepon, Mockery::on(fn (string $message): bool => str_contains($message, 'reset-password')))
                ->andReturn([
                    'success' => true,
                    'message' => 'Pesan berhasil dikirim ke Fonnte.',
                    'data' => [],
                ]);
        });

        $this->post('/forgot-password', ['no_telepon' => $user->no_telepon])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/login')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_login_screen_shows_a_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Lupa kata sandi?')
            ->assertSee(route('password.request'));
    }
}
