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
use App\Http\Controllers\Logs\LogTranslationController;
use App\Http\Controllers\Logs\StaticLogsController;
use App\Http\Controllers\Static\RosterController;
use App\Http\Controllers\Static\StaticRosterController;
use App\Http\Controllers\Settings\StaticSettingsController;
use App\Http\Controllers\Gear\GearController;
use App\Http\Controllers\Treasury\TreasuryController;
use App\Http\Controllers\Api\DiscordGuildController;
use App\Http\Controllers\Auth\BattleNetController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FeedbackCommentController;
use App\Http\Controllers\FeedbackSubtaskController;
use App\Http\Controllers\FeedbackUploadController;
use App\Http\Controllers\FeedbackVoteController;
use App\Services\Backup\BackupHealthService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/language/switch', [LanguageController::class, 'switch'])->name('language.switch');

// Lightweight health/ping endpoint.
// Default: returns {"message":"ok"} with no Sentry side-effect — safe for uptime monitors.
// Pass ?throw=1 to raise an uncaught exception end-to-end (for GlitchTip pipeline testing).
Route::get('/ping', function (\Illuminate\Http\Request $request) {
    if ($request->boolean('throw')) {
        throw new \RuntimeException('GlitchTip ping ('.uniqid().')');
    }

    return response()->json(['message' => 'ok']);
})->name('ping');

// Backup freshness — polled by GlitchTip Uptime Monitor. 200 while every
// destination has a backup within BackupHealthService::FRESHNESS_THRESHOLD_HOURS,
// 503 with details otherwise.
Route::get('/health/backup', function (BackupHealthService $service) {
    $result = $service->check();

    return response()->json($result, $result['status'] === 'ok' ? 200 : 503);
})->name('health.backup');

Route::middleware(['auth', 'verified', 'ensure_has_static', 'resolve_current_static'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Characters
    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters/import', [CharacterController::class, 'import'])->name('characters.import');
    Route::post('/characters/assign', [CharacterController::class, 'assignToStatic'])->name('characters.assign');
    Route::post('/characters/specs', [CharacterSpecController::class, 'update'])->name('characters.specs.update');
    Route::get('/personal-reports', [CharacterController::class, 'personalReports'])->name('personal-reports');

    // Roster
    Route::get('/roster', [RosterController::class, 'index'])->name('statics.roster');
    Route::get('/roster/weekly-snapshot', [RosterController::class, 'weeklySnapshot'])->name('statics.roster.weekly-snapshot');
    Route::get('/roster/overview', [RosterController::class, 'overview'])->name('statics.roster.overview');
    Route::patch('/roster/{user}/access-role', [StaticRosterController::class, 'updateAccessRole'])->name('statics.roster.update-access');
    Route::patch('/roster/{user}/roster-status', [StaticRosterController::class, 'updateRosterStatus'])->name('statics.roster.update-status');
    Route::delete('/roster/{user}/kick', [StaticRosterController::class, 'kick'])->name('statics.roster.kick');

    // Treasury
    Route::get('/treasury', [TreasuryController::class, 'index'])->name('statics.treasury');
    Route::get('/treasury/history', [TreasuryController::class, 'history'])->name('statics.treasury.history');
    Route::post('/treasury', [TreasuryController::class, 'store'])->name('statics.treasury.store');
    Route::post('/consumables', [TreasuryController::class, 'updateConsumables'])->name('consumables.store');
    Route::patch('/treasury/{transaction}', [TreasuryController::class, 'update'])->name('statics.treasury.update');
    Route::patch('/treasury-settings', [TreasuryController::class, 'updateSettings'])->name('statics.treasury.settings.update');

    // Gear Management
    Route::get('/gear', [GearController::class, 'index'])->name('statics.gear');

    // Settings
    Route::get('/settings/profile', [StaticSettingsController::class, 'profile'])->name('statics.settings.profile');
    Route::get('/settings/schedule', [StaticSettingsController::class, 'schedule'])->name('statics.settings.schedule');
    Route::patch('/settings/schedule', [StaticSettingsController::class, 'updateSchedule'])->name('statics.settings.schedule.update');
    Route::get('/settings/discord', [StaticSettingsController::class, 'discord'])->name('statics.settings.discord');
    Route::patch('/settings/discord', [StaticSettingsController::class, 'updateDiscord'])->name('statics.settings.discord.update');
    Route::post('/settings/discord/test', [StaticSettingsController::class, 'testDiscordWebhook'])->name('statics.settings.discord.test');
    Route::post('/settings/discord/test-channel', [StaticSettingsController::class, 'testDiscordChannel'])->name('statics.settings.discord.test-channel');
    Route::delete('/settings/discord/channel-message/{messageId}', [StaticSettingsController::class, 'deleteChannelMessage'])->name('statics.settings.discord.channel-message.delete');
    Route::delete('/settings/discord/message/{messageId}', [StaticSettingsController::class, 'deleteWebhookMessage'])->name('statics.settings.discord.message.delete');
    Route::post('/settings/discord/test-notification-channel', [StaticSettingsController::class, 'testNotificationChannel'])->name('statics.settings.discord.test-notification-channel');
    Route::delete('/settings/discord/notification-channel-message/{messageId}', [StaticSettingsController::class, 'deleteNotificationChannelMessage'])->name('statics.settings.discord.notification-channel-message.delete');
    Route::get('/settings/logs', [StaticSettingsController::class, 'logs'])->name('statics.settings.logs');
    Route::post('/settings/logs', [StaticSettingsController::class, 'updateLogs'])->name('statics.settings.logs.update');
    Route::post('/settings/logs/connect-guild', [StaticSettingsController::class, 'connectGuild'])->name('statics.settings.logs.connect-guild');
    Route::post('/settings/logs/disconnect-guild', [StaticSettingsController::class, 'disconnectGuild'])->name('statics.settings.logs.disconnect-guild');

    // Logs
    Route::get('/logs', [StaticLogsController::class, 'index'])->name('statics.logs.index');
    Route::post('/logs/manual', [StaticLogsController::class, 'storeManual'])->name('statics.logs.manual.store');
    Route::get('/logs/{report}', [StaticLogsController::class, 'show'])->name('statics.logs.show');

    // Static management
    Route::post('/invite', [StaticController::class, 'generateInvite'])->name('statics.invite.generate');

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
    Route::post('/schedule/event/{event}/assign-plan', [EventController::class, 'assignPlan'])->name('schedule.event.assign-plan');
    Route::post('/schedule/event/{event}/toggle-encounter', [EventController::class, 'toggleEncounter'])->name('schedule.event.toggle-encounter');
    Route::post('/schedule/event/{event}/settings', [EventController::class, 'updateSettings'])->name('schedule.event.settings');
    Route::post('/schedule/event/{event}/override-attendance', [EventController::class, 'overrideAttendance'])->name('schedule.event.override-attendance');
    Route::post('/schedule/event/{event}/save-splits', [EventController::class, 'saveSplitAssignments'])->name('schedule.event.save-splits');

    // Boss Planner (standalone section)
    Route::get('/boss-planner', [BossPlannerController::class, 'index'])->name('statics.boss-planner');
    Route::post('/boss-planner/save', [BossPlannerController::class, 'save'])->name('statics.boss-planner.save');
    Route::delete('/boss-planner/{raidPlan}', [BossPlannerController::class, 'destroy'])->name('statics.boss-planner.destroy');
    Route::post('/boss-planner/{raidPlan}/share', [BossPlannerController::class, 'share'])->name('statics.boss-planner.share');
    Route::post('/boss-planner/{raidPlan}/unshare', [BossPlannerController::class, 'unshare'])->name('statics.boss-planner.unshare');
    Route::post('/boss-planner/character/{character}/cooldown-toggle', [BossPlannerController::class, 'toggleCharacterCooldown'])->name('statics.boss-planner.character.cooldown-toggle');

    // Discord Guild API
    Route::get('/api/discord/guilds/{guildId}/channels', [DiscordGuildController::class, 'channels']);
    Route::get('/api/discord/guilds/{guildId}/roles', [DiscordGuildController::class, 'roles']);
});

Route::middleware(['auth', 'resolve_current_static'])->group(function () {

    // AI Analyst
    Route::post('/api/logs/analyze', [AiAnalystController::class, 'ask']);
    Route::post('/api/logs/personal/{personalReport}/translate', [LogTranslationController::class, 'personal'])->name('statics.logs.personal.translate');

    // Roster participation
    Route::post('/participation', [RosterController::class, 'updateParticipation'])->name('roster.updateParticipation');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/privacy', [ProfileController::class, 'updatePrivacy'])->name('profile.privacy.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Discord linking
    Route::get('/discord/bot-invited', fn () => view('discord.bot-invited'))->name('discord.bot-invited');
    Route::get('/profile/discord/link', [DiscordLinkController::class, 'link'])->name('profile.discord.link');
    Route::get('/profile/discord/callback', [DiscordLinkController::class, 'callback'])->name('profile.discord.callback');
    Route::post('/profile/discord/unlink', [DiscordLinkController::class, 'unlink'])->name('profile.discord.unlink');

    // Static membership
    Route::delete('/profile/static/leave', [StaticMembershipController::class, 'leaveStatic'])->name('profile.static.leave');
    Route::post('/profile/static/transfer', [StaticMembershipController::class, 'transferOwnership'])->name('profile.static.transfer');

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

// ----- Feedback / Roadmap --------------------------------------------------
// Public browsing (guests see everything, but can't act).
// Each top-level section gets its own URL so that /roadmap, /changelog, /help
// can be linked independently.
Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
Route::get('/feedback/{post}', [FeedbackController::class, 'show'])->name('feedback.show');
Route::get('/roadmap', [FeedbackController::class, 'roadmap'])->name('feedback.roadmap');

// User-only actions — require a full Battle.net login (Auth::check()).
// The controller re-checks to produce a clean JSON 401 for AJAX.
Route::middleware('auth')->group(function () {
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::post('/feedback/{post}/vote', [FeedbackVoteController::class, 'toggle'])->name('feedback.vote');
    Route::post('/feedback/{post}/comments', [FeedbackCommentController::class, 'store'])->name('feedback.comments.store');
    Route::post('/feedback/uploads', [FeedbackUploadController::class, 'store'])->name('feedback.uploads');
});

// Owner-or-admin + admin-only actions. No `auth` middleware so that an admin
// logged in on the admin subdomain (session flag only, no Battle.net user)
// can still manage posts. Authorization happens inside controllers.
Route::patch('/feedback/{post}', [FeedbackController::class, 'update'])->name('feedback.update');
Route::delete('/feedback/{post}', [FeedbackController::class, 'destroy'])->name('feedback.destroy');
Route::delete('/feedback/comments/{comment}', [FeedbackCommentController::class, 'destroy'])->name('feedback.comments.destroy');

Route::patch('/feedback/{post}/status', [FeedbackController::class, 'updateStatus'])->name('feedback.status');
Route::post('/feedback/{post}/subtasks', [FeedbackSubtaskController::class, 'store'])->name('feedback.subtasks.store');
Route::patch('/feedback/subtasks/{subtask}', [FeedbackSubtaskController::class, 'update'])->name('feedback.subtasks.update');
Route::delete('/feedback/subtasks/{subtask}', [FeedbackSubtaskController::class, 'destroy'])->name('feedback.subtasks.destroy');
Route::post('/feedback/{post}/subtasks/reorder', [FeedbackSubtaskController::class, 'reorder'])->name('feedback.subtasks.reorder');

require __DIR__.'/auth.php';
