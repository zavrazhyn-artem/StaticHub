<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StaticGroup;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_page_displays_grouped_characters()
    {
        $user = User::factory()->create(['name' => 'Player One', 'battletag' => 'Player#1234']);
        $realm = \App\Models\Realm::create(['name' => 'Silvermoon', 'slug' => 'silvermoon', 'region' => 'eu']);
        $static = StaticGroup::create([
            'name' => 'Loot & Taxes',
            'slug' => 'loot-taxes',
            'server' => 'Silvermoon',
            'owner_id' => $user->id,
        ]);

        // Ensure user belongs to the static (to pass has_static middleware)
        $user->statics()->attach($static, ['role' => 'owner']);

        $mainChar = Character::create([
            'id' => 1, 'user_id' => $user->id, 'name' => 'MainChar', 'realm_id' => $realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
            'avatar_url' => 'https://avatar.url/main'
        ]);
        $altChar = Character::create([
            'id' => 2, 'user_id' => $user->id, 'name' => 'AltChar', 'realm_id' => $realm->id,
            'playable_class' => 'Mage', 'playable_race' => 'Human', 'level' => 80,
            'avatar_url' => 'https://avatar.url/alt'
        ]);

        $mainChar->statics()->attach($static, ['role' => 'main', 'combat_role' => 'tank']);
        $altChar->statics()->attach($static, ['role' => 'alt', 'combat_role' => 'rdps']);

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
            'server' => 'Silvermoon',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

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
        $realm = \App\Models\Realm::create(['name' => 'Silvermoon', 'slug' => 'silvermoon', 'region' => 'eu']);
        $static = StaticGroup::create([
            'name' => 'Test Static',
            'slug' => 'test-static',
            'server' => 'Silvermoon',
            'owner_id' => $user->id,
        ]);
        $user->statics()->attach($static, ['role' => 'owner']);

        $char1 = Character::create([
            'id' => 10, 'user_id' => $user->id, 'name' => 'Char1', 'realm_id' => $realm->id,
            'playable_class' => 'Paladin', 'playable_race' => 'Human', 'level' => 80,
        ]);
        $char2 = Character::create([
            'id' => 11, 'user_id' => $user->id, 'name' => 'Char2', 'realm_id' => $realm->id,
            'playable_class' => 'Mage', 'playable_race' => 'Human', 'level' => 80,
        ]);

        $response = $this->actingAs($user)->post(route('roster.updateParticipation'), [
            'main_character_id' => $char1->id,
            'raiding_characters' => [$char1->id, $char2->id],
            'combat_roles' => [
                $char1->id => 'tank',
                $char2->id => 'rdps',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
            'combat_role' => 'tank',
        ]);
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char2->id,
            'static_id' => $static->id,
            'role' => 'alt',
            'combat_role' => 'rdps',
        ]);

        // Update again, removing char2 and changing role of char1
        $response = $this->actingAs($user)->post(route('roster.updateParticipation'), [
            'main_character_id' => $char1->id,
            'raiding_characters' => [$char1->id],
            'combat_roles' => [
                $char1->id => 'mdps',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('character_static', [
            'character_id' => $char1->id,
            'static_id' => $static->id,
            'role' => 'main',
            'combat_role' => 'mdps',
        ]);
        $this->assertDatabaseMissing('character_static', [
            'character_id' => $char2->id,
            'static_id' => $static->id,
        ]);
    }
}
