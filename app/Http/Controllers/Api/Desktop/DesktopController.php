<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Desktop\AddonReleaseService;
use App\Services\Desktop\DesktopSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Single endpoint surface for the BlastR Desktop bridge.
 *
 *   GET   /api/desktop/sync       — heartbeat: wishlists + settings + schedule + manifest
 *   PATCH /api/desktop/settings   — partial update of the user's bridge settings
 *   GET   /api/desktop/addon      — streamed addon zip (auth-gated)
 *
 * Every route is sanctum-authenticated (PAT issued via the device
 * authorization flow). The web UI uses the same endpoints under the
 * same auth — settings edited from the website land in the same JSON
 * column the bridge reads on its next heartbeat.
 */
final class DesktopController extends Controller
{
    public function __construct(
        private readonly DesktopSyncService $svc,
        private readonly AddonReleaseService $addons,
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
            'auto_update_addons_enabled'   => ['sometimes', 'boolean'],
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

    /**
     * Stream the current addon zip to an authenticated bridge. The
     * file lives in the Laravel repo (resources/desktop/addon.zip) so
     * a release ships with the regular deploy — no separate publish
     * step. Bridges always come through the bearer-token PAT flow,
     * which keeps the binary off the public internet.
     *
     * Returns 404 when the release file is missing — useful when
     * spinning up a fresh environment before the first build script
     * has run; bridges will see the manifest's null download_url and
     * skip the install attempt entirely.
     */
    public function downloadAddon(Request $request): BinaryFileResponse
    {
        if (! $this->addons->exists()) {
            abort(404, 'Addon release file is not yet published');
        }
        return $this->addons->streamResponse();
    }
}
