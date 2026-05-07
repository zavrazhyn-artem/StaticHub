<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\WishlistSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bridge-facing read-only endpoint. Returns the full wishlist snapshot
 * for the authenticated user's static, formatted exactly as the Lua
 * SavedVariables file the bridge will write.
 *
 * Phase 1 always returns the full set; the optional `since` parameter is
 * accepted but only filters wishlists with a newer imported_at, which is
 * cheap server-side. The bridge currently overwrites the SV file on
 * every poll, so partial-snapshot semantics aren't critical yet.
 */
final class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistSyncService $svc,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['sometimes', 'string', 'date'],
        ]);

        $since = isset($data['since']) ? CarbonImmutable::parse($data['since']) : null;
        $payload = $this->svc->buildPayload($request->user(), $since);

        return response()->json($payload);
    }
}
