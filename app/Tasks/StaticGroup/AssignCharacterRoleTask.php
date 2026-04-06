<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\Specialization;
use App\Models\User;

class AssignCharacterRoleTask
{
    /**
     * Assign a character to a static with a specific role and handle auto-downgrade.
     */
    public function run(int $characterId, int $staticId, string $role, int $userId): void
    {
        $character = Character::findOrFail($characterId);

        $user = User::findOrFail($userId);
        if (!$user->statics()->where('statics.id', $staticId)->exists()) {
            throw new \Exception('You are not a member of this static.');
        }

        if ($role === 'main') {
            Character::downgradeMainToAlt($userId, $staticId);
        }

        $character->statics()->syncWithoutDetaching([
            $staticId => ['role' => $role],
        ]);

        $this->autoSetMainSpecIfMissing($character, $staticId);
    }

    /**
     * Auto-set main spec from active_spec on first assignment to a static.
     */
    public function autoSetMainSpecIfMissing(Character $character, int $staticId): void
    {
        $hasSpecs = CharacterStaticSpec::where('character_id', $character->id)
            ->where('static_id', $staticId)
            ->exists();

        if ($hasSpecs) {
            return;
        }

        if (!$character->active_spec || !$character->playable_class) {
            return;
        }

        $spec = Specialization::where('name', $character->active_spec)
            ->where('class_name', $character->playable_class)
            ->first();

        if (!$spec) {
            return;
        }

        CharacterStaticSpec::create([
            'character_id' => $character->id,
            'static_id'    => $staticId,
            'spec_id'      => $spec->id,
            'is_main'      => true,
        ]);
    }
}
