<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Realm;
use App\Models\StaticGroup;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private Realm $realm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realm = Realm::create(['name' => 'Silvermoon', 'slug' => 'silvermoon', 'region' => 'eu']);
    }

    public function test_roster_page_displays_grouped_characters()
    {
        $user = User::factory()->create(['name' => 'Player One', 'battletag' => 'Player#1234']);
        $static = StaticGroup::create([
            'name' => 'Loot & Taxes',
            'slug' => 'loot-taxes',
            'owner_id' => $user->id,
        ]);

        $user->statics()->attach($static, ['role' => 'owner']);

        $mainChar = Character::create([
            'id' => 1, 'user_id' => $user->id, 'name' => 'MainChar', 'realm_id' => $this->realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
            'avatar_url' => 'https://avatar.url/main'
        ]);
        $altChar = Character::create([
            'id' => 2, 'user_id' => $user->id, 'name' => 'AltChar', 'realm_id' => $this->realm->id,
            'playable_class' => 'Mage', 'playable_race' => 'Human', 'level' => 80,
            'avatar_url' => 'https://avatar.url/alt'
        ]);

        $mainChar->statics()->attach($static, ['role' => 'main']);
        $altChar->statics()->attach($static, ['role' => 'alt']);

        $response = $this->actingAs($user)->get(route('statics.roster'));

        $response->assertStatus(200);
        $response->assertSee('Loot & Taxes');
        $response->assertSee('MainChar');
        $response->assertSee('Paladin');
        $response->assertSee('AltChar');
        $response->assertSee('Mage');
    }

    public function test_navigation_menu_contains_roster_link()
    {
        $user = User::factory()->create();
        $static = StaticGroup::create([
            'name' => 'Loot & Taxes',
            'slug' => 'loot-taxes',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $char = Character::create([
            'user_id' => $user->id, 'name' => 'NavChar', 'realm_id' => $this->realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
        ]);
        $char->statics()->attach($static, ['role' => 'main']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('statics.roster'));
        $response->assertSee('Roster');
        $response->assertSee(route('characters.index'));
        $response->assertSee('Characters');
    }

    public function test_user_can_update_participation()
    {
        $user = User::factory()->create();
        $static = StaticGroup::create([
            'name' => 'Test Static',
            'slug' => 'test-static',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $char1 = Character::create([
            'id' => 10, 'user_id' => $user->id, 'name' => 'Char1', 'realm_id' => $this->realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
        ]);
        $char2 = Character::create([
            'id' => 11, 'user_id' => $user->id, 'name' => 'Char2', 'realm_id' => $this->realm->id,
            'playable_class' => 'Mage', 'playable_race' => 'Human', 'level' => 80,
        ]);

        // Attach one character first so middleware doesn't redirect to onboarding
        $char1->statics()->attach($static, ['role' => 'main']);

        $response = $this->actingAs($user)->post(route('roster.updateParticipation'), [
            'main_character_id' => $char1->id,
            'raiding_characters' => [$char1->id, $char2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char2->id,
            'static_id' => $static->id,
            'role' => 'alt',
        ]);

        // Update again, removing char2
        $response = $this->actingAs($user)->post(route('roster.updateParticipation'), [
            'main_character_id' => $char1->id,
            'raiding_characters' => [$char1->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
        ]);
        $this->assertDatabaseMissing('character_static', [
            'character_id' => $char2->id,
            'static_id' => $static->id,
        ]);
    }
}
