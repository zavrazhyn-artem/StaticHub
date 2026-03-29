<?php

use App\Http\Middleware\VerifyDiscordSignature;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DiscordInteractionController;

Route::post('/discord/interactions', [DiscordInteractionController::class, 'handle'])
    ->middleware(VerifyDiscordSignature::class);
