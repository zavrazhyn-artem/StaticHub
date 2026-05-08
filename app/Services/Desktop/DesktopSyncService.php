<?php

declare(strict_types=1);

namespace App\Services\Desktop;

use App\Models\Event;
use App\Models\User;
use App\Services\Sync\WishlistSyncService;
use Carbon\CarbonImmutable;

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
     * @return array<string, mixed>
     */
    private function resolveManifest(): array
    {
        return [
            'bridge' => config('blastr_desktop.bridge'),
            'addon'  => config('blastr_desktop.addon'),
        ];
    }
}
