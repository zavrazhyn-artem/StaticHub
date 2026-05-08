<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\LootHistorySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push endpoint: bridge POSTs an array of loot-award events from a
 * full-scan of `RCLootCouncilLootDB`. Idempotency is on `external_id`
 * (RC's own per-award id), so the bridge can re-push the same scan
 * cheaply on every cycle without producing duplicates.
 */
final class LootHistoryController extends Controller
{
    public function __construct(
        private readonly LootHistorySyncService $svc,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events'                          => 'required|array|min:1|max:500',
            'events.*.external_id'            => 'required|string|max:64',
            'events.*.awarded_at'             => 'required|string',
            'events.*.recipient'              => 'required|string|max:128',
            'events.*.method'                 => 'required|string|in:bis,ms,os,trash,free',
            'events.*.item.id'                => 'required|integer|min:1',

            'events.*.raid.slug'              => 'sometimes|string|max:64',
            'events.*.raid.difficulty'        => 'sometimes|string|max:4',
            'events.*.raid.boss'              => 'sometimes|nullable|string|max:128',
            'events.*.raid.encounter_id'      => 'sometimes|nullable|integer|min:0',
            'events.*.raid.pull_id'           => 'sometimes|nullable|string|max:64',

            'events.*.recipient_class'        => 'sometimes|nullable|string|max:16',
            'events.*.recipient_spec_id'      => 'sometimes|nullable|integer|min:0',
            'events.*.awarded_by'             => 'sometimes|nullable|string|max:128',

            'events.*.item.ilvl'              => 'sometimes|nullable|integer|min:0',
            'events.*.item.bonus_ids'         => 'sometimes|nullable|array',
            'events.*.item.enchant'           => 'sometimes|nullable|integer|min:0',
            'events.*.item.gems'              => 'sometimes|nullable|array',

            'events.*.response.text'          => 'sometimes|nullable|string|max:64',
            'events.*.response.color'         => 'sometimes|nullable|string|max:32',
            'events.*.council_same_vote'      => 'sometimes|nullable|integer|min:0',
            'events.*.is_award_reason'        => 'sometimes|boolean',
            'events.*.is_test_mode'           => 'sometimes|boolean',
            'events.*.note'                   => 'sometimes|nullable|string',
        ]);

        $result = $this->svc->ingest($request->user(), $data['events']);
        return response()->json($result);
    }
}
