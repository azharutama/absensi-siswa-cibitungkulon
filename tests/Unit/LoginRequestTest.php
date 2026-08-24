<?php

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use PHPUnit\Framework\TestCase;

class LoginRequestTest extends TestCase
{
    public function test_throttle_key_normalizes_case_and_surrounding_whitespace(): void
    {
        $spaced = LoginRequest::create('/login', 'POST', ['login' => ' UserName ']);
        $normalized = LoginRequest::create('/login', 'POST', ['login' => 'username']);

        $this->assertSame($normalized->throttleKey(), $spaced->throttleKey());
    }
}
