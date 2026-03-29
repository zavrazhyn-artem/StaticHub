<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaticSettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\BattleNetController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\ScheduleController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', 'has_static'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'showFirst'])->name('dashboard');

    Route::get('/consumables', [App\Http\Controllers\ConsumablesController::class, 'index'])->name('consumables.index');
    Route::post('/consumables', [App\Http\Controllers\ConsumablesController::class, 'store'])->name('consumables.store');

    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters/import', [CharacterController::class, 'import'])->name('characters.import');
    Route::post('/characters/assign', [CharacterController::class, 'assignToStatic'])->name('characters.assign');

    Route::post('/statics/{static}/participation', [RosterController::class, 'updateParticipation'])->name('roster.updateParticipation');

    Route::get('/statics/{static}/dashboard', [App\Http\Controllers\DashboardController::class, 'show'])->name('statics.dashboard');
    Route::get('/statics/{static}/roster', [RosterController::class, 'index'])->name('statics.roster');
    Route::get('/statics/{static}/treasury', [App\Http\Controllers\TreasuryController::class, 'index'])->name('statics.treasury');
    Route::post('/statics/{static}/treasury', [App\Http\Controllers\TreasuryController::class, 'store'])->name('statics.treasury.store');

    Route::get('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'schedule'])->name('statics.settings.schedule');
    Route::post('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'updateSchedule'])->name('statics.settings.schedule.update');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::get('/schedule/event/{event}', [App\Http\Controllers\RaidEventController::class, 'show'])->name('schedule.event.show');
    Route::post('/schedule/event/{event}/rsvp', [App\Http\Controllers\RaidEventController::class, 'rsvp'])->name('schedule.event.rsvp');
    Route::post('/schedule/event/{event}/announce', [App\Http\Controllers\RaidEventController::class, 'announceToDiscord'])->name('schedule.announce');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/profile/discord/link', [ProfileController::class, 'linkDiscord'])->name('profile.discord.link');
    Route::get('/profile/discord/callback', [ProfileController::class, 'discordCallback'])->name('profile.discord.callback');
    Route::post('/profile/discord/unlink', [ProfileController::class, 'unlinkDiscord'])->name('profile.discord.unlink');

    Route::get('/statics/setup', [StaticController::class, 'index'])->name('statics.setup');
    Route::post('/statics', [StaticController::class, 'store'])->name('statics.store');
    Route::post('/statics/import', [StaticController::class, 'importGuild'])->name('statics.import');
});

// Роути для авторизації через Battle.net
Route::get('/auth/battlenet/redirect', [BattleNetController::class, 'redirect'])->name('battlenet.redirect');
Route::get('/auth/battlenet/callback', [BattleNetController::class, 'callback'])->name('battlenet.callback');

require __DIR__.'/auth.php';
