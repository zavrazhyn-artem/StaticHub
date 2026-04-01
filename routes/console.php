<?php

use App\Console\Commands\FetchAuctionsCommand;
use App\Models\StaticGroup;
use App\Jobs\SyncStaticGroupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ProcessDiscordAutomations;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(FetchAuctionsCommand::class)->hourly();
Schedule::command(ProcessDiscordAutomations::class)->everyFifteenMinutes();
Schedule::command('statics:sync-rosters')->hourly();
Schedule::command('app:fetch-bonus-ids')->weekly();

Schedule::call(function () {
    $statics = StaticGroup::all();

    foreach ($statics as $static) {
        SyncStaticGroupJob::dispatch($static);
    }
})->everyMinute();

