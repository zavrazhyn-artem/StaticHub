<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->markTestSkipped('App does not have password reset routes.');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $this->markTestSkipped('App does not have password reset routes.');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $this->markTestSkipped('App does not have password reset routes.');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $this->markTestSkipped('App does not have password reset routes.');
    }
}
