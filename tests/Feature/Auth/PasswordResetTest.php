<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_reset_link_can_be_requested_by_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
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
