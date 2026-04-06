<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCharacterSpecsRequest;
use App\Models\Character;
use App\Models\CharacterStaticSpec;
use Illuminate\Http\JsonResponse;

class CharacterSpecController extends Controller
{
    /**
     * Update the available specs and main spec for a character in a static.
     */
    public function update(UpdateCharacterSpecsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $characterId = (int) $validated['character_id'];
        $staticId    = (int) $validated['static_id'];
        $specIds     = array_map('intval', $validated['spec_ids']);
        $mainSpecId  = (int) $validated['main_spec_id'];

        // Ensure main_spec_id is in spec_ids
        if (!in_array($mainSpecId, $specIds, true)) {
            $specIds[] = $mainSpecId;
        }

        // Delete old entries for this character+static
        CharacterStaticSpec::where('character_id', $characterId)
            ->where('static_id', $staticId)
            ->delete();

        // Insert new ones
        foreach ($specIds as $specId) {
            CharacterStaticSpec::create([
                'character_id' => $characterId,
                'static_id'    => $staticId,
                'spec_id'      => $specId,
                'is_main'      => $specId === $mainSpecId,
            ]);
        }

        // Return the updated main spec for immediate frontend update
        $character = Character::find($characterId);
        $mainSpec  = $character?->getMainSpecInStatic($staticId);

        return response()->json([
            'success'   => true,
            'main_spec' => $mainSpec ? [
                'id'       => $mainSpec->id,
                'name'     => $mainSpec->name,
                'role'     => $mainSpec->role,
                'icon_url' => $mainSpec->icon_url,
            ] : null,
        ]);
    }
}
