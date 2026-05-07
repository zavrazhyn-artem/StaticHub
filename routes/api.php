<?php

use App\Http\Controllers\AiAnalystController;
use App\Http\Controllers\Api\DiscordGuildController;
use App\Http\Controllers\Api\DiscordInteractionController;
use App\Http\Controllers\Api\Sync\OAuthDeviceController;
use App\Http\Controllers\Api\Sync\LootHistoryController;
use App\Http\Controllers\Api\Sync\WishlistController;
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

// Wishlist pull — bridge polls this on its 30s loop and transcodes the
// payload into the BlastR_RCLootCouncil SavedVariables file.
Route::middleware('auth:sanctum')
    ->get('/v1/sync/wishlists', [WishlistController::class, 'index'])
    ->name('api.sync.wishlists');

// Loot-history push — bridge drains BlastROutboxEvents into this and
// drops the events from the SV on a 200 response. Idempotent on
// per-event UUIDs so retries are safe.
Route::middleware('auth:sanctum')
    ->post('/v1/sync/loot-history', [LootHistoryController::class, 'store'])
    ->name('api.sync.loot-history');
