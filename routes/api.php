<?php

use App\Http\Controllers\AiAnalystController;
use App\Http\Controllers\Api\Desktop\DesktopController;
use App\Http\Controllers\Api\DiscordGuildController;
use App\Http\Controllers\Api\DiscordInteractionController;
use App\Http\Controllers\Api\Sync\OAuthDeviceController;
use App\Http\Controllers\Api\Sync\LootHistoryController;
use App\Http\Middleware\VerifyDiscordSignature;
use Illuminate\Support\Facades\Route;

Route::post('/discord/interactions', [DiscordInteractionController::class, 'handle'])
    ->middleware(VerifyDiscordSignature::class);

// BlastR Desktop bridge — RFC 8628 Device Authorization Grant.
// Both endpoints are unauthenticated (the bridge has no credentials yet);
// throttle protects against abuse and naive scripted abuse of /token.
Route::prefix('v1/oauth/device')->group(function () {
    Route::post('/code', [OAuthDeviceController::class, 'requestCode'])
        ->middleware('throttle:30,1')
        ->name('api.oauth.device.code');
    Route::post('/token', [OAuthDeviceController::class, 'poll'])
        ->middleware('throttle:120,1')
        ->name('api.oauth.device.token');
});

// Bridge identity probe — first call after pairing. Confirms the PAT works
// and lets the bridge cache who it's syncing as.
Route::middleware('auth:sanctum')
    ->get('/v1/sync/me', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        return response()->json([
            'id'        => $user->id,
            'name'      => $user->name,
            'battletag' => $user->battletag,
            'avatar'    => $user->avatar,
        ]);
    })
    ->name('api.sync.me');

// Bridge unified heartbeat: wishlists + settings + schedule + manifest.
// Replaces the old /v1/sync/wishlists — bridge does one round-trip per
// cycle and gets everything it needs (data to write, runtime knobs to
// honour, next raid for pre-RT sync, version manifest for self-update).
//
// Drain ingest endpoints also live under /api/desktop/ so the bridge's
// trust-boundary check (post_url must start with /api/desktop/) covers
// every spec-driven POST in one rule. New ingest = add a route here +
// a SyncSpecService entry, no bridge update needed.
Route::middleware('auth:sanctum')->prefix('desktop')->group(function () {
    Route::get('/sync', [DesktopController::class, 'sync'])->name('api.desktop.sync');
    Route::patch('/settings', [DesktopController::class, 'updateSettings'])->name('api.desktop.settings.update');
    Route::get('/addon', [DesktopController::class, 'downloadAddon'])->name('api.desktop.addon.download');
    Route::get('/bridge', [DesktopController::class, 'downloadBridge'])->name('api.desktop.bridge.download');
    Route::post('/loot-history', [LootHistoryController::class, 'store'])->name('api.desktop.loot-history');
});

// Legacy alias kept so existing pre-spec bridges (anything before the
// Phase A spec runner) keep working until everyone has updated.
Route::middleware('auth:sanctum')
    ->post('/v1/sync/loot-history', [LootHistoryController::class, 'store'])
    ->name('api.sync.loot-history');
