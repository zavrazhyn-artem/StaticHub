<?php

declare(strict_types=1);

namespace App\Http\Controllers\Character;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateCharacterSpecsRequest;
use App\Services\Character\CharacterService;
use Illuminate\Http\JsonResponse;

class CharacterSpecController extends Controller
{
    public function __construct(
        private readonly CharacterService $characterService
    ) {}

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

        $this->characterService->updateSpecs($characterId, $staticId, $specIds, $mainSpecId);

        $mainSpec = $this->characterService->getMainSpecInStatic($characterId, $staticId);

        return response()->json([
            'success'   => true,
            'main_spec' => $mainSpec,
        ]);
    }
}
