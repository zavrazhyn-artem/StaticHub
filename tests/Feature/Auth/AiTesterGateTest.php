<?php

namespace Tests\Feature\Auth;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTesterGateTest extends TestCase
{
    use RefreshDatabase;

    // phpunit.xml sets AI_ACCESS=ai-tester-secret-test, so the route registers.
    private const KEY = 'ai-tester-secret-test';

    public function test_logs_in_target_user_by_id_and_marks_session(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('ai_tester', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_resolves_user_by_battletag(): void
    {
        $user = User::factory()->create(['battletag' => 'Zavrikk#2956']);

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.urlencode('Zavrikk#2956'))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_defaults_to_first_static_owner(): void
    {
        $owner = User::factory()->create();
        StaticGroup::factory()->create(['owner_id' => $owner->id]);

        $this->get('/auth/ai-tester?key='.self::KEY)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_honours_relative_redirect_only(): void
    {
        $user = User::factory()->create();

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id.'&redirect=/roster')
            ->assertRedirect('/roster');

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id.'&redirect=https://evil.test')
            ->assertRedirect(route('dashboard'));
    }

    public function test_404_when_key_wrong(): void
    {
        User::factory()->create();
        $this->get('/auth/ai-tester?key=wrong')->assertNotFound();
        $this->assertGuest();
    }

    public function test_404_when_key_unconfigured(): void
    {
        config(['ai_access.key' => '']);
        $this->get('/auth/ai-tester?key='.self::KEY)->assertNotFound();
        $this->assertGuest();
    }

    public function test_404_in_production_even_with_key(): void
    {
        $this->app['env'] = 'production';
        User::factory()->create();
        $this->get('/auth/ai-tester?key='.self::KEY)->assertNotFound();
        $this->assertGuest();
    }
}
