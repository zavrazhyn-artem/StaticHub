<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->markTestSkipped('App uses Battle.net OAuth — no email/password login route.');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $this->markTestSkipped('App uses Battle.net OAuth — no email/password login route.');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $this->markTestSkipped('App uses Battle.net OAuth — no email/password login route.');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
