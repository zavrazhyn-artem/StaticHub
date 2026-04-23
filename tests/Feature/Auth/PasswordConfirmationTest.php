<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $this->markTestSkipped('App does not have a password confirmation route.');
    }

    public function test_password_can_be_confirmed(): void
    {
        $this->markTestSkipped('App does not have a password confirmation route.');
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $this->markTestSkipped('App does not have a password confirmation route.');
    }
}
