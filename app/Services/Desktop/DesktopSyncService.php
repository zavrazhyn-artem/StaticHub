<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use App\Models\Event;
use App\Models\User;
use App\Services\Sync\WishlistSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * Builds the unified payload that `GET /api/desktop/sync` returns to
 * the BlastR Desktop bridge on every heartbeat.
 *
 * One round-trip carries everything the bridge needs: the wishlist
 * snapshot it writes to SavedVariables, the user's editable runtime
 * settings (poll cadence, pre-raid behaviour, auto-update toggle),
 * the next scheduled raid for the user's static (so the bridge can
 * arm a local timer for the pre-raid sync push), and the release
 * manifest the self-updater compares against its own version.
 *
 * Wishlists are delegated to {@see WishlistSyncService} verbatim —
 * this service is purely an envelope so we can evolve the rest
 * without touching the wishlist contract.
 */
final class DesktopSyncService
{
    public function __construct(
        private readonly WishlistSyncService $wishlists,
        private readonly AddonReleaseService $addons,
        private readonly SyncSpecService $spec,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user, ?CarbonImmutable $since = null): array
    {
        $wishlists = $this->wishlists->buildPayload($user, $since);

        return [
            'schema'    => $wishlists['schema'],
            'synced_at' => $wishlists['synced_at'],
            'team_id'   => $wishlists['team_id'],
            'wishlists' => [
                'characters' => $wishlists['characters'],
            ],
            'settings'  => $user->getDesktopSettings(),
            'schedule'  => $this->resolveSchedule($user),
            'manifest'  => $this->resolveManifest(),
            'spec'      => $this->spec->build($wishlists),
        ];
    }

    /**
     * Next raid for the user's primary static. Bridge arms a local
     * timer at `next_raid_at - pre_raid_sync_offset_minutes` and
     * forces a fresh sync + Windows toast at fire-time.
     *
     * @return array{next_raid_at:?string, next_raid_event_id:?int}
     */
    private function resolveSchedule(User $user): array
    {
        $static = $user->statics()->orderBy('statics.id')->first();
        if ($static === null) {
            return ['next_raid_at' => null, 'next_raid_event_id' => null];
        }

        $event = Event::query()->nextRaid($static->id);
        if ($event === null) {
            return ['next_raid_at' => null, 'next_raid_event_id' => null];
        }

        return [
            'next_raid_at'       => $event->start_time->toIso8601String(),
            'next_raid_event_id' => $event->id,
        ];
    }

    /**
     * Bridge manifest stays env-driven (releases live on our server,
     * no automation yet). Addon manifest is computed live from the
     * file in `resources/desktop/`: replacing the zip is enough to
     * publish a new version, no env edits needed.
     *
     * @return array<string, mixed>
     */
    private function resolveManifest(): array
    {
        $addonVersion = $this->addons->version();
        $addonSha     = $this->addons->sha256();
        $addonReady   = $this->addons->exists();

        return [
            'bridge' => config('blastr_desktop.bridge'),
            'addon'  => [
                'latest_version' => $addonVersion,
                // URL points at the auth-protected download endpoint;
                // bridge sends Bearer token + gets the bytes streamed.
                // Null when no zip is published yet — bridge skips
                // install attempt rather than 404-ing.
                'download_url'   => $addonReady ? URL::route('api.desktop.addon.download') : null,
                'sha256'         => $addonSha,
            ],
        ];
    }
}
