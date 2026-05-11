<?php

use App\Console\Commands\FetchAuctionsCommand;
use App\Console\Commands\GenerateEventsCommand;
use App\Console\Commands\ProcessUserActivityLogsCommand;
use App\Console\Commands\PruneAuctionHistoryCommand;
use App\Console\Commands\PurgeOldLogsCommand;
use App\Console\Commands\PurgeUserActivityLogsCommand;
use App\Console\Commands\RefreshAllCharactersCommand;
use App\Jobs\StaticGroup\DispatchSyncOrchestratorJob;
use App\Models\StaticGroup;
use App\Jobs\SyncStaticGroupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ProcessDiscordAutomations;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(FetchAuctionsCommand::class)->hourly()->withoutOverlapping(30)->onOneServer();
Schedule::command(ProcessDiscordAutomations::class)->everyMinute()->withoutOverlapping(5)->onOneServer();
// Scheduler tick just queues the orchestrator on `default`; the actual
//// statics scan + bnet/rio dispatch runs on a worker pod. Manual admin runs
//// of `statics:sync` are still available via SyncAllStaticsCommand.
Schedule::job(new DispatchSyncOrchestratorJob)->everyMinute()->onOneServer();
// Daily lightweight ilvl/spec refresh for every char — keeps My Characters page
// current for chars that aren't in any static (and so aren't covered by the
// orchestrator's per-static cadence).
Schedule::command(RefreshAllCharactersCommand::class)->dailyAt('04:00')->onOneServer();
Schedule::command(GenerateEventsCommand::class)->daily()->at('00:00')->onOneServer();

// Weekly reset — snapshot character_weekly_data and clear it
Schedule::command('weekly:reset eu')->weeklyOn(3, '04:01'); // Wednesday 04:01 UTC
Schedule::command('weekly:reset us')->weeklyOn(2, '15:01'); // Tuesday   15:01 UTC
Schedule::command('weekly:reset kr')->weeklyOn(3, '02:01'); // Wednesday 02:01 UTC
Schedule::command('weekly:reset tw')->weeklyOn(3, '02:01'); // Wednesday 02:01 UTC

// MySQL backups handled by DO Managed MySQL (daily + 7-day point-in-time
// recovery, included in the $21 plan). spatie/laravel-backup left
// uninstalled — its DB dump duplicated what DO already does, less reliably.

Schedule::command(PurgeOldLogsCommand::class)->daily()->at('05:00');

Schedule::command(ProcessUserActivityLogsCommand::class)->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command(PurgeUserActivityLogsCommand::class)->daily()->at('05:15')->onOneServer();

// Delete raid payload files older than 24h (locks chat activation for those reports).
Schedule::command('ai:cleanup-payloads')->dailyAt('03:30')->onOneServer();

// Prune price_snapshots — keep last 7 days of auction history. Only the
// latest snapshot per item is read by ConsumableService; older rows are
// dead weight. The table is on track for 12M+ rows otherwise.
Schedule::command(PruneAuctionHistoryCommand::class)->dailyAt('03:45')->onOneServer();

