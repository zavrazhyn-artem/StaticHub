<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Services\StaticGroup\DiscordCacheService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('discord:sync-cached-names {--id= : Refresh a single static_group id}')]
#[Description('Refresh discord_cached_names (guild/channel/role/webhook labels) for static groups')]
class SyncDiscordCachedNamesCommand extends Command
{
    public function handle(DiscordCacheService $cache): int
    {
        $query = StaticGroup::query()
            ->whereNotNull('discord_guild_id')
            ->orWhereNotNull('discord_webhook_url');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $statics = $query->get();

        if ($statics->isEmpty()) {
            $this->info('No statics with Discord configuration found.');
            return self::SUCCESS;
        }

        $this->info("Refreshing Discord cache for {$statics->count()} static(s)...");

        $ok = 0;
        $failed = 0;

        foreach ($statics as $static) {
            try {
                $cache->refreshAll($static);
                $this->line("  ✓ #{$static->id} {$static->name}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  ✗ #{$static->id} {$static->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Refreshed: {$ok}, failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
