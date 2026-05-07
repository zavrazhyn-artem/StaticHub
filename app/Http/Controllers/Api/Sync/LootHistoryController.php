<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\LootHistorySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push endpoint: bridge POSTs an array of loot-award events read from
 * the addon's BlastR_RCLootCouncil.lua outbox. We accept everything
 * that has a UUID + valid awarded_at; bad events are reported in
 * `rejected[]` so the bridge can decide whether to drop or re-try.
 */
final class LootHistoryController extends Controller
{
    public function __construct(
        private readonly LootHistorySyncService $svc,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events'               => 'required|array|min:1|max:200',
            'events.*.event_uuid'  => 'required|string|max:64',
            'events.*.awarded_at'  => 'required|string',
            'events.*.recipient'   => 'required|string|max:128',
            'events.*.method'      => 'required|string|in:bis,ms,os,trash,free',
            'events.*.item.id'     => 'required|integer|min:1',
            'events.*.raid.slug'   => 'sometimes|string|max:64',
            'events.*.raid.difficulty' => 'sometimes|string|max:4',
        ]);

        $result = $this->svc->ingest($request->user(), $data['events']);
        return response()->json($result);
    }
}
