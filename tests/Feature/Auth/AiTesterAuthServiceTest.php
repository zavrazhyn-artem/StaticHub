<?php

namespace Tests\Feature\Auth;

use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Auth\AiTesterAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTesterAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AiTesterAuthService
    {
        return app(AiTesterAuthService::class);
    }

    public function test_verify_key_matches_configured_secret(): void
    {
        config(['ai_access.key' => 'ai-tester-secret-test']);
        $this->assertTrue($this->service()->verifyKey('ai-tester-secret-test'));
        $this->assertFalse($this->service()->verifyKey('wrong'));
        $this->assertFalse($this->service()->verifyKey(null));
    }

    public function test_verify_key_is_false_when_unconfigured(): void
    {
        config(['ai_access.key' => '']);
        $this->assertFalse($this->service()->verifyKey(''));
        $this->assertFalse($this->service()->verifyKey('anything'));
    }

    public function test_resolves_user_by_id_and_battletag(): void
    {
        $user = User::factory()->create(['battletag' => 'Zavrikk#2956']);
        $this->assertEquals($user->id, $this->service()->resolveUser((string) $user->id)?->id);
        $this->assertEquals($user->id, $this->service()->resolveUser('Zavrikk#2956')?->id);
        $this->assertNull($this->service()->resolveUser('Nope#0000'));
    }

    public function test_defaults_to_first_static_owner_when_no_identity(): void
    {
        $owner = User::factory()->create();
        StaticGroup::factory()->create(['owner_id' => $owner->id]);
        $this->assertEquals($owner->id, $this->service()->resolveUser(null)?->id);
    }

    public function test_empty_string_identity_falls_back_to_first_static_owner(): void
    {
        $owner = User::factory()->create();
        StaticGroup::factory()->create(['owner_id' => $owner->id]);
        $this->assertEquals($owner->id, $this->service()->resolveUser('')?->id);
    }

    public function test_returns_null_when_no_identity_and_no_static_exists(): void
    {
        $this->assertNull($this->service()->resolveUser(null));
    }
}
