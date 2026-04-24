<?php

use App\Console\Commands\FetchAuctionsCommand;
use App\Console\Commands\GenerateEventsCommand;
use App\Console\Commands\ProcessUserActivityLogsCommand;
use App\Console\Commands\PurgeOldLogsCommand;
use App\Console\Commands\PurgeUserActivityLogsCommand;
use App\Console\Commands\SyncAllStaticsCommand;
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
Schedule::command(SyncAllStaticsCommand::class)->everyMinute()->withoutOverlapping(10)->onOneServer();
Schedule::command(GenerateEventsCommand::class)->daily()->at('00:00')->onOneServer();

// Weekly reset — snapshot character_weekly_data and clear it
Schedule::command('weekly:reset eu')->weeklyOn(3, '04:01'); // Wednesday 04:01 UTC
Schedule::command('weekly:reset us')->weeklyOn(2, '15:01'); // Tuesday   15:01 UTC
Schedule::command('weekly:reset kr')->weeklyOn(3, '02:01'); // Wednesday 02:01 UTC
Schedule::command('weekly:reset tw')->weeklyOn(3, '02:01'); // Wednesday 02:01 UTC

Schedule::command('backup:run --only-db')->daily()->at('03:00');

// Очищуємо старі бекапи (rotation), щоб не закінчилося місце
Schedule::command('backup:clean')->daily()->at('04:00');

Schedule::command(PurgeOldLogsCommand::class)->daily()->at('05:00');

Schedule::command(ProcessUserActivityLogsCommand::class)->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command(PurgeUserActivityLogsCommand::class)->daily()->at('05:15')->onOneServer();

