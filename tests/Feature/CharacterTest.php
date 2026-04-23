<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Realm;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Character\CharacterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CharacterTest extends TestCase
{
    use RefreshDatabase;

    private Realm $realm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realm = Realm::create(['name' => 'Silvermoon', 'slug' => 'silvermoon', 'region' => 'eu']);
    }

    public function test_user_can_import_characters()
    {
        $user = User::factory()->create();
        $static = StaticGroup::create([
            'name' => 'Test Static',
            'slug' => 'test-static',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $realm = $this->realm;
        $this->instance(CharacterSyncService::class, \Mockery::mock(CharacterSyncService::class, function (MockInterface $mock) use ($user, $realm) {
            $mock->shouldReceive('syncUserCharacters')
                ->once()
                ->andReturnUsing(function() use ($user, $realm) {
                    Character::create([
                        'id' => 12345,
                        'user_id' => $user->id,
                        'name' => 'TestChar',
                        'realm_id' => $realm->id,
                        'playable_class' => 'Paladin',
                        'playable_race' => 'Human',
                        'level' => 80,
                        'avatar_url' => 'https://avatar.url',
                    ]);
                });
        }));

        $response = $this->actingAs($user)
            ->withSession(['battlenet_token' => 'fake-token'])
            ->post(route('characters.import'));

        $response->assertRedirect();
        $this->assertDatabaseHas('characters', [
            'id' => 12345,
            'name' => 'TestChar',
            'user_id' => $user->id,
            'level' => 80,
            'avatar_url' => 'https://avatar.url',
        ]);
    }

    public function test_only_one_main_per_user_per_static()
    {
        $user = User::factory()->create();
        $static = StaticGroup::create([
            'name' => 'Test Static',
            'slug' => 'test-static',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $char1 = Character::create([
            'id' => 1, 'user_id' => $user->id, 'name' => 'Char1', 'realm_id' => $this->realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
        ]);
        $char2 = Character::create([
            'id' => 2, 'user_id' => $user->id, 'name' => 'Char2', 'realm_id' => $this->realm->id,
            'playable_class' => 'Mage', 'playable_race' => 'Human', 'level' => 80,
        ]);

        // Assign char1 as main
        $this->actingAs($user)->post(route('characters.assign'), [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);

        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);

        // Assign char2 as main
        $this->actingAs($user)->post(route('characters.assign'), [
            'character_id' => $char2->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);

        // char1 should be downgraded to alt
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'alt',
        ]);

        // char2 should be the new main
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char2->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);
    }

    public function test_user_can_assign_character_to_static()
    {
        $user = User::factory()->create();
        $static = StaticGroup::create([
            'name' => 'Test Static',
            'slug' => 'test-static',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $character = Character::create([
            'id' => 12345,
            'user_id' => $user->id,
            'name' => 'TestChar',
            'realm_id' => $this->realm->id,
            'playable_class' => 'Paladin',
            'playable_race' => 'Human',
            'level' => 80,
        ]);

        $response = $this->actingAs($user)->post(route('characters.assign'), [
            'character_id' => $character->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('character_static', [
            'character_id' => $character->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);
    }
}
