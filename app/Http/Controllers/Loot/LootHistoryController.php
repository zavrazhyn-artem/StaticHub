<?php

declare(strict_types=1);

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Services\Loot\LootHistoryStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON payload for the Loot History tab inside the Gear page. Lazy-loaded
 * by LootHistoryPanel.vue when the user clicks the tab — keeps the gear
 * page initial response small even when a static has thousands of awards.
 */
final class LootHistoryController extends Controller
{
    public function __construct(
        private readonly LootHistoryStatsService $stats,
    ) {}

    public function payload(Request $request, StaticGroup $static): JsonResponse
    {
        $payload = $this->stats->buildLootHistoryPayload($static, $request->query());

        // Paginator → array so it serialises with current_page/total/etc.
        $rows = $payload['rows'];
        $payload['rows'] = $rows->items();
        $payload['pagination'] = [
            'current_page' => $rows->currentPage(),
            'last_page'    => $rows->lastPage(),
            'per_page'     => $rows->perPage(),
            'total'        => $rows->total(),
            'next_url'     => $rows->nextPageUrl(),
            'prev_url'     => $rows->previousPageUrl(),
        ];
        unset($payload['static']);

        return response()->json($payload);
    }
}
