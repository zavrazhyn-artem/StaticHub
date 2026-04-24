<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Services\Discord\DiscordMessageService;
use App\Services\Discord\DiscordWebhookService;

class DiscordCacheService
{
    public function __construct(
        private readonly DiscordMessageService $discord,
        private readonly DiscordWebhookService $webhook,
    ) {}

    /**
     * Re-fetch everything from Discord and persist the full cache.
     * Used by the deploy backfill command and after a webhook URL change.
     *
     * @return array The updated cached_names blob.
     */
    public function refreshAll(StaticGroup $static): array
    {
        try {
            $context = $this->discord->getDiscordContext(
                $static->owner?->discord_id,
                $static->discord_guild_id,
            );
        } catch (\Throwable) {
            $context = ['botGuilds' => [], 'discordChannels' => [], 'discordRoles' => []];
        }

        $cached = $this->applyFromContext($static, $context);

        return $this->refreshWebhookChannel($static, $cached);
    }

    /**
     * Update guild/channel/role names from an already-fetched Discord context.
     * Existing cache entries are preserved when the fresh data does not contain a saved id
     * (e.g. user lost access) so we never silently lose a label.
     *
     * @return array The updated cached_names blob.
     */
    public function applyFromContext(StaticGroup $static, array $context): array
    {
        $cached = $static->discord_cached_names ?? [];

        $guildMap   = collect($context['botGuilds']       ?? [])->keyBy('id');
        $channelMap = collect($context['discordChannels'] ?? [])->keyBy('id');
        $roleMap    = collect($context['discordRoles']    ?? [])->keyBy('id');

        $cached['guild']                = $this->resolveEntry((string) $static->discord_guild_id, $guildMap, $cached['guild'] ?? null);
        $cached['announcement_channel'] = $this->resolveEntry((string) $static->discord_channel_id, $channelMap, $cached['announcement_channel'] ?? null);
        $cached['notification_channel'] = $this->resolveEntry((string) $static->notification_channel_id, $channelMap, $cached['notification_channel'] ?? null);

        $pingRoleIds = $static->automation_settings['ping_role_ids'] ?? [];
        $existingRoles = collect($cached['ping_roles'] ?? [])->keyBy('id');
        $cached['ping_roles'] = collect($pingRoleIds)
            ->map(fn ($id) => $this->resolveEntry((string) $id, $roleMap, $existingRoles[(string) $id] ?? null))
            ->filter()
            ->values()
            ->all();

        $static->update(['discord_cached_names' => $cached]);

        return $cached;
    }

    /**
     * Resolve the webhook channel name and persist it. Falls back to existing cache on failure.
     */
    public function refreshWebhookChannel(StaticGroup $static, ?array $cached = null): array
    {
        $cached ??= $static->discord_cached_names ?? [];

        if (empty($static->discord_webhook_url)) {
            $cached['webhook_channel'] = null;
            $static->update(['discord_cached_names' => $cached]);
            return $cached;
        }

        try {
            $resolved = $this->webhook->resolveWebhookChannel($static->discord_webhook_url);
            if ($resolved) {
                $cached['webhook_channel'] = $resolved;
            }
        } catch (\Throwable) {
            // keep stale cache entry
        }

        $static->update(['discord_cached_names' => $cached]);

        return $cached;
    }

    /**
     * Look up an id in the fresh map; fall back to the existing cached entry when missing.
     *
     * @param  \Illuminate\Support\Collection<string, array>  $freshMap
     */
    private function resolveEntry(string $id, $freshMap, ?array $existing): ?array
    {
        if ($id === '') {
            return null;
        }

        if ($freshMap->has($id)) {
            return ['id' => $id, 'name' => $freshMap[$id]['name']];
        }

        return $existing;
    }
}
