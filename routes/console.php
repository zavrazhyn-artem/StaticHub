<?php

use App\Console\Commands\FetchAuctionsCommand;
use App\Console\Commands\GenerateEventsCommand;
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

Schedule::command(FetchAuctionsCommand::class)->everyFifteenMinutes();
Schedule::command(ProcessDiscordAutomations::class)->everyMinute();
Schedule::command(SyncAllStaticsCommand::class)->everyMinute();
Schedule::command(GenerateEventsCommand::class)->daily()->at('00:00');

Schedule::command('backup:run --only-db')->daily()->at('03:00');

// Очищуємо старі бекапи (rotation), щоб не закінчилося місце
Schedule::command('backup:clean')->daily()->at('04:00');

