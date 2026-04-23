<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $this->markTestSkipped('App does not have a /password update route.');
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $this->markTestSkipped('App does not have a /password update route.');
    }
}
