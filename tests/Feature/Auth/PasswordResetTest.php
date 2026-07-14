<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_forgot_password_routes_are_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'operator@gmail.com'])->assertNotFound();
    }

    public function test_reset_password_routes_are_not_available(): void
    {
        $this->get('/reset-password/invalid-token')->assertNotFound();
        $this->post('/reset-password')->assertNotFound();
    }

    public function test_login_screen_does_not_show_a_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Lupa sandi?')
            ->assertDontSee('/forgot-password');
    }
}
