<?php

use App\Http\Controllers\AiAnalystController;
use App\Http\Controllers\Character\CharacterController;
use App\Http\Controllers\Character\CharacterSpecController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Profile\DiscordLinkController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\StaticMembershipController;
use App\Http\Controllers\Raid\BossPlannerController;
use App\Http\Controllers\Raid\EventController;
use App\Http\Controllers\Raid\ScheduleController;
use App\Http\Controllers\Static\JoinStaticController;
use App\Http\Controllers\Static\StaticController;
use App\Http\Controllers\Logs\StaticLogsController;
use App\Http\Controllers\Static\RosterController;
use App\Http\Controllers\Static\StaticRosterController;
use App\Http\Controllers\Settings\StaticSettingsController;
use App\Http\Controllers\Treasury\TreasuryController;
use App\Http\Controllers\Api\DiscordGuildController;
use App\Http\Controllers\Auth\BattleNetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');

Route::middleware(['auth', 'verified', 'ensure_has_static'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'showFirst'])->name('dashboard');
    Route::get('/statics/{static}/dashboard', [DashboardController::class, 'show'])->name('statics.dashboard');

    // Characters
    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters/import', [CharacterController::class, 'import'])->name('characters.import');
    Route::post('/characters/assign', [CharacterController::class, 'assignToStatic'])->name('characters.assign');
    Route::post('/characters/specs', [CharacterSpecController::class, 'update'])->name('characters.specs.update');
    Route::get('/personal-reports', [CharacterController::class, 'personalReports'])->name('personal-reports');

    // Roster
    Route::get('/statics/{static}/roster', [RosterController::class, 'index'])->name('statics.roster');
    Route::get('/statics/{static}/roster/weekly-snapshot', [RosterController::class, 'weeklySnapshot'])->name('statics.roster.weekly-snapshot');
    Route::get('/statics/{static}/roster/overview', [RosterController::class, 'overview'])->name('statics.roster.overview');
    Route::patch('/statics/{static}/roster/{user}/access-role', [StaticRosterController::class, 'updateAccessRole'])->name('statics.roster.update-access');
    Route::patch('/statics/{static}/roster/{user}/roster-status', [StaticRosterController::class, 'updateRosterStatus'])->name('statics.roster.update-status');
    Route::delete('/statics/{static}/roster/{user}/kick', [StaticRosterController::class, 'kick'])->name('statics.roster.kick');

    // Treasury
    Route::get('/statics/{static}/treasury', [TreasuryController::class, 'index'])->name('statics.treasury');
    Route::get('/statics/{static}/treasury/history', [TreasuryController::class, 'history'])->name('statics.treasury.history');
    Route::post('/statics/{static}/treasury', [TreasuryController::class, 'store'])->name('statics.treasury.store');
    Route::post('/statics/{static}/consumables', [TreasuryController::class, 'updateConsumables'])->name('consumables.store');
    Route::patch('/statics/{static}/treasury/{transaction}', [TreasuryController::class, 'update'])->name('statics.treasury.update');
    Route::patch('/statics/{static}/treasury-settings', [TreasuryController::class, 'updateSettings'])->name('statics.treasury.settings.update');

    // Settings
    Route::get('/statics/{static}/settings/profile', [StaticSettingsController::class, 'profile'])->name('statics.settings.profile');
    Route::get('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'schedule'])->name('statics.settings.schedule');
    Route::patch('/statics/{static}/settings/schedule', [StaticSettingsController::class, 'updateSchedule'])->name('statics.settings.schedule.update');
    Route::get('/statics/{static}/settings/discord', [StaticSettingsController::class, 'discord'])->name('statics.settings.discord');
    Route::patch('/statics/{static}/settings/discord', [StaticSettingsController::class, 'updateDiscord'])->name('statics.settings.discord.update');
    Route::post('/statics/{static}/settings/discord/test', [StaticSettingsController::class, 'testDiscordWebhook'])->name('statics.settings.discord.test');
    Route::post('/statics/{static}/settings/discord/test-channel', [StaticSettingsController::class, 'testDiscordChannel'])->name('statics.settings.discord.test-channel');
    Route::delete('/statics/{static}/settings/discord/channel-message/{messageId}', [StaticSettingsController::class, 'deleteChannelMessage'])->name('statics.settings.discord.channel-message.delete');
    Route::delete('/statics/{static}/settings/discord/message/{messageId}', [StaticSettingsController::class, 'deleteWebhookMessage'])->name('statics.settings.discord.message.delete');
    Route::post('/statics/{static}/settings/discord/test-notification-channel', [StaticSettingsController::class, 'testNotificationChannel'])->name('statics.settings.discord.test-notification-channel');
    Route::delete('/statics/{static}/settings/discord/notification-channel-message/{messageId}', [StaticSettingsController::class, 'deleteNotificationChannelMessage'])->name('statics.settings.discord.notification-channel-message.delete');
    Route::get('/statics/{static}/settings/logs', [StaticSettingsController::class, 'logs'])->name('statics.settings.logs');
    Route::post('/statics/{static}/settings/logs', [StaticSettingsController::class, 'updateLogs'])->name('statics.settings.logs.update');
    Route::post('/statics/{static}/settings/logs/connect-guild', [StaticSettingsController::class, 'connectGuild'])->name('statics.settings.logs.connect-guild');
    Route::post('/statics/{static}/settings/logs/disconnect-guild', [StaticSettingsController::class, 'disconnectGuild'])->name('statics.settings.logs.disconnect-guild');

    // Logs
    Route::get('/statics/{static}/logs', [StaticLogsController::class, 'index'])->name('statics.logs.index');
    Route::post('/statics/{static}/logs/manual', [StaticLogsController::class, 'storeManual'])->name('statics.logs.manual.store');
    Route::get('/statics/{static}/logs/{report}', [StaticLogsController::class, 'show'])->name('statics.logs.show');

    // Static management
    Route::post('/statics/{static}/invite', [StaticController::class, 'generateInvite'])->name('statics.invite.generate');

    // Schedule & Events
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::patch('/schedule/event/{event}', [ScheduleController::class, 'update'])->name('schedule.event.update');
    Route::delete('/schedule/event/{event}', [ScheduleController::class, 'destroy'])->name('schedule.event.destroy');
    Route::get('/schedule/event/{event}', [EventController::class, 'show'])->name('schedule.event.show');
    Route::post('/schedule/event/{event}/rsvp', [EventController::class, 'rsvp'])->name('schedule.event.rsvp');
    Route::post('/schedule/event/{event}/announce', [EventController::class, 'announceToDiscord'])->name('schedule.announce');

    // Event Encounter Roster
    Route::post('/schedule/event/{event}/encounter-roster', [EventController::class, 'updateEncounterRoster'])->name('schedule.event.encounter-roster.update');
    Route::post('/schedule/event/{event}/encounter-roster/assign', [EventController::class, 'assignEncounterCharacter'])->name('schedule.event.encounter-roster.assign');
    Route::delete('/schedule/event/{event}/encounter-roster/remove', [EventController::class, 'removeEncounterCharacter'])->name('schedule.event.encounter-roster.remove');

    // Boss Planner (standalone section)
    Route::get('/statics/{static}/boss-planner', [BossPlannerController::class, 'index'])->name('statics.boss-planner');
    Route::post('/statics/{static}/boss-planner/save', [BossPlannerController::class, 'save'])->name('statics.boss-planner.save');
    Route::delete('/statics/{static}/boss-planner/{raidPlan}', [BossPlannerController::class, 'destroy'])->name('statics.boss-planner.destroy');
    Route::post('/statics/{static}/boss-planner/{raidPlan}/share', [BossPlannerController::class, 'share'])->name('statics.boss-planner.share');
    Route::post('/statics/{static}/boss-planner/{raidPlan}/unshare', [BossPlannerController::class, 'unshare'])->name('statics.boss-planner.unshare');

    // Discord Guild API
    Route::get('/api/discord/guilds/{guildId}/channels', [DiscordGuildController::class, 'channels']);
    Route::get('/api/discord/guilds/{guildId}/roles', [DiscordGuildController::class, 'roles']);
});

Route::middleware('auth')->group(function () {

    // AI Analyst
    Route::post('/api/logs/analyze', [AiAnalystController::class, 'ask']);

    // Roster participation
    Route::post('/statics/{static}/participation', [RosterController::class, 'updateParticipation'])->name('roster.updateParticipation');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Discord linking
    Route::get('/discord/bot-invited', fn () => view('discord.bot-invited'))->name('discord.bot-invited');
    Route::get('/profile/discord/link', [DiscordLinkController::class, 'link'])->name('profile.discord.link');
    Route::get('/profile/discord/callback', [DiscordLinkController::class, 'callback'])->name('profile.discord.callback');
    Route::post('/profile/discord/unlink', [DiscordLinkController::class, 'unlink'])->name('profile.discord.unlink');

    // Static membership
    Route::delete('/profile/static/leave', [StaticMembershipController::class, 'leaveStatic'])->name('profile.static.leave');
    Route::post('/profile/static/{static}/transfer', [StaticMembershipController::class, 'transferOwnership'])->name('profile.static.transfer');

    // Static setup
    Route::get('/statics/setup', [StaticController::class, 'index'])->name('statics.setup');
    Route::post('/statics', [StaticController::class, 'store'])->name('statics.store');
    Route::post('/statics/import', [StaticController::class, 'importGuild'])->name('statics.import');

    // Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/create', [OnboardingController::class, 'createStatic'])->name('onboarding.create');
    Route::post('/onboarding/validate-invite-code', [OnboardingController::class, 'validateInviteCode'])->name('onboarding.validate-invite-code');
    Route::post('/onboarding/validate-token', [OnboardingController::class, 'validateToken'])->name('onboarding.validate-token');
    Route::post('/onboarding/join', [OnboardingController::class, 'joinStatic'])->name('onboarding.join');
    Route::post('/onboarding/sync-characters', [OnboardingController::class, 'syncCharacters'])->name('onboarding.sync-characters');
    Route::post('/onboarding/save-participation', [OnboardingController::class, 'saveParticipation'])->name('onboarding.save-participation');

    // Join static (authenticated — process join)
    Route::post('/join/{token}', [JoinStaticController::class, 'processJoin'])->name('statics.join.process');
});

// Join static — public landing page (no auth required)
Route::get('/join/{token}', [JoinStaticController::class, 'showLanding'])->name('statics.join');
Route::get('/plan/{token}', [BossPlannerController::class, 'shared'])->name('plan.shared');

// Battle.net OAuth
Route::get('/auth/battlenet/redirect', [BattleNetController::class, 'redirect'])->name('battlenet.redirect');
Route::get('/auth/battlenet/callback', [BattleNetController::class, 'callback'])->name('battlenet.callback');

require __DIR__.'/auth.php';
