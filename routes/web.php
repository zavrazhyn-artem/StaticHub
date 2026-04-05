<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\JoinStaticController;
use App\Http\Controllers\StaticSettingsController;
use App\Http\Controllers\StaticLogsController;
use App\Http\Controllers\Api\DiscordGuildController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\BattleNetController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\ScheduleController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/language/switch', [App\Http\Controllers\LanguageController::class, 'switch'])->name('language.switch');

Route::middleware(['auth', 'verified', 'ensure_has_static'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'showFirst'])->name('dashboard');


    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters/import', [CharacterController::class, 'import'])->name('characters.import');
    Route::post('/characters/assign', [CharacterController::class, 'assignToStatic'])->name('characters.assign');
    Route::get('/personal-reports', [CharacterController::class, 'personalReports'])->name('personal-reports');

    Route::get('/statics/{static}/dashboard', [App\Http\Controllers\DashboardController::class, 'show'])->name('statics.dashboard');
    Route::get('/statics/{static}/roster', [RosterController::class, 'index'])->name('statics.roster');
    Route::patch('/statics/{static}/roster/{user}/access-role', [App\Http\Controllers\StaticGroupRosterController::class, 'updateAccessRole'])->name('statics.roster.update-access');
    Route::patch('/statics/{static}/roster/{user}/roster-status', [App\Http\Controllers\StaticGroupRosterController::class, 'updateRosterStatus'])->name('statics.roster.update-status');
    Route::delete('/statics/{static}/roster/{user}/kick', [App\Http\Controllers\StaticGroupRosterController::class, 'kick'])->name('statics.roster.kick');
    Route::get('/statics/{static}/roster/overview', [RosterController::class, 'overview'])->name('statics.roster.overview');
    Route::get('/statics/{static}/treasury', [App\Http\Controllers\TreasuryController::class, 'index'])->name('statics.treasury');
    Route::post('/statics/{static}/treasury', [App\Http\Controllers\TreasuryController::class, 'store'])->name('statics.treasury.store');
    Route::post('/statics/{static}/consumables', [App\Http\Controllers\TreasuryController::class, 'updateConsumables'])->name('consumables.store');
    Route::patch('/statics/{static}/treasury/{transaction}', [App\Http\Controllers\TreasuryController::class, 'update'])->name('statics.treasury.update');
    Route::patch('/statics/{static}/treasury-settings', [App\Http\Controllers\TreasuryController::class, 'updateSettings'])->name('statics.treasury.settings.update');

    Route::get('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'schedule'])->name('statics.settings.schedule');
    Route::post('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'updateSchedule'])->name('statics.settings.schedule.update');

    Route::get('/statics/{static}/settings/discord', [StaticSettingsController::class, 'discord'])->name('statics.settings.discord');
    Route::patch('/statics/{static}/settings/discord', [StaticSettingsController::class, 'updateDiscord'])->name('statics.settings.discord.update');
    Route::post('/statics/{static}/settings/discord/test', [StaticSettingsController::class, 'testDiscordWebhook'])->name('statics.settings.discord.test');
    Route::delete('/statics/{static}/settings/discord/message/{messageId}', [StaticSettingsController::class, 'deleteWebhookMessage'])->name('statics.settings.discord.message.delete');

    Route::get('/statics/{static}/settings/logs', [StaticSettingsController::class, 'logs'])->name('statics.settings.logs');
    Route::post('/statics/{static}/settings/logs', [StaticSettingsController::class, 'updateLogs'])->name('statics.settings.logs.update');

    Route::get('/statics/{static}/logs', [StaticLogsController::class, 'index'])->name('statics.logs.index');
    Route::post('/statics/{static}/logs/manual', [App\Http\Controllers\LogAnalysisController::class, 'storeManual'])->name('statics.logs.manual.store');
    Route::get('/statics/{static}/logs/{report}', [StaticLogsController::class, 'show'])->name('statics.logs.show');

    Route::post('/statics/{static}/invite', [App\Http\Controllers\StaticController::class, 'generateInvite'])->name('statics.invite.generate');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::patch('/schedule/event/{event}', [ScheduleController::class, 'update'])->name('schedule.event.update');
    Route::delete('/schedule/event/{event}', [ScheduleController::class, 'destroy'])->name('schedule.event.destroy');
    Route::get('/schedule/event/{event}', [App\Http\Controllers\RaidEventController::class, 'show'])->name('schedule.event.show');
    Route::post('/schedule/event/{event}/rsvp', [App\Http\Controllers\RaidEventController::class, 'rsvp'])->name('schedule.event.rsvp');
    Route::post('/schedule/event/{event}/announce', [App\Http\Controllers\RaidEventController::class, 'announceToDiscord'])->name('schedule.announce');

    Route::get('/api/discord/guilds/{guildId}/channels', [DiscordGuildController::class, 'channels']);
    Route::get('/api/discord/guilds/{guildId}/roles',    [DiscordGuildController::class, 'roles']);
});

Route::middleware('auth')->group(function () {

    Route::post('/statics/{static}/participation', [RosterController::class, 'updateParticipation'])->name('roster.updateParticipation');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/profile/discord/link', [ProfileController::class, 'linkDiscord'])->name('profile.discord.link');
    Route::get('/profile/discord/callback', [ProfileController::class, 'discordCallback'])->name('profile.discord.callback');
    Route::post('/profile/discord/unlink', [ProfileController::class, 'unlinkDiscord'])->name('profile.discord.unlink');

    Route::delete('/profile/static/leave', [ProfileController::class, 'leaveStatic'])->name('profile.static.leave');
    Route::post('/profile/static/{static}/transfer', [ProfileController::class, 'transferOwnership'])->name('profile.static.transfer');

    Route::get('/statics/setup', [StaticController::class, 'index'])->name('statics.setup');
    Route::post('/statics', [StaticController::class, 'store'])->name('statics.store');
    Route::post('/statics/import', [StaticController::class, 'importGuild'])->name('statics.import');

    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/create', [OnboardingController::class, 'createStatic'])->name('onboarding.create');

    Route::get('/join/{token}', [JoinStaticController::class, 'showJoinPage'])->name('statics.join');
    Route::post('/join/{token}', [JoinStaticController::class, 'processJoin'])->name('statics.join.process');
});

// Роути для авторизації через Battle.net
Route::get('/auth/battlenet/redirect', [BattleNetController::class, 'redirect'])->name('battlenet.redirect');
Route::get('/auth/battlenet/callback', [BattleNetController::class, 'callback'])->name('battlenet.callback');

require __DIR__.'/auth.php';
