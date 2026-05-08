<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Desktop\DesktopSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single endpoint surface for the BlastR Desktop bridge.
 *
 *   GET  /api/desktop/sync       — heartbeat: wishlists + settings + schedule + manifest
 *   PATCH /api/desktop/settings  — partial update of the user's bridge settings
 *
 * Both routes are sanctum-authenticated (PAT issued via the device
 * authorization flow). The web UI uses the same endpoints under the
 * same auth — settings edited from the website land in the same JSON
 * column the bridge reads on its next heartbeat.
 */
final class DesktopController extends Controller
{
    public function __construct(
        private readonly DesktopSyncService $svc,
    ) {}

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['sometimes', 'string', 'date'],
        ]);

        $since = isset($data['since']) ? CarbonImmutable::parse($data['since']) : null;
        return response()->json($this->svc->buildPayload($request->user(), $since));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'sync_interval_minutes'        => ['sometimes', 'integer', 'min:15', 'max:1440'],
            'pre_raid_sync_enabled'        => ['sometimes', 'boolean'],
            'pre_raid_sync_offset_minutes' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'auto_update_enabled'          => ['sometimes', 'boolean'],
        ]);

        // Merge into existing settings so partial PATCH keeps untouched
        // fields intact. getDesktopSettings() applies defaults so first
        // write after migration starts from a known-good baseline.
        $merged = array_replace($user->getDesktopSettings(), $data);
        $user->desktop_settings = $merged;
        $user->save();

        return response()->json([
            'settings' => $merged,
        ]);
    }
}
