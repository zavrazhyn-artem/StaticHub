<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\BattleNetController;
use App\Http\Controllers\StaticController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified', 'has_static'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/consumables', [App\Http\Controllers\ConsumablesController::class, 'index'])->name('consumables.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/statics/setup', [StaticController::class, 'index'])->name('statics.setup');
    Route::post('/statics', [StaticController::class, 'store'])->name('statics.store');
    Route::post('/statics/import', [StaticController::class, 'importGuild'])->name('statics.import');
});

// Роути для авторизації через Battle.net
Route::get('/auth/battlenet/redirect', [BattleNetController::class, 'redirect'])->name('battlenet.redirect');
Route::get('/auth/battlenet/callback', [BattleNetController::class, 'callback'])->name('battlenet.callback');

require __DIR__.'/auth.php';
