<?php

use App\Http\Controllers\Admin\AdminAiLogController;
use App\Http\Controllers\Admin\AdminApiLogController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminInviteCodeController;
use Illuminate\Support\Facades\Route;

// Public admin routes
Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Protected admin routes
Route::middleware('admin_auth')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/ai-logs', [AdminAiLogController::class, 'index'])->name('admin.ai-logs');
    Route::get('/api-logs', [AdminApiLogController::class, 'index'])->name('admin.api-logs');

    Route::get('/invite-codes', [AdminInviteCodeController::class, 'index'])->name('admin.invite-codes');
    Route::post('/invite-codes/generate', [AdminInviteCodeController::class, 'generate'])->name('admin.invite-codes.generate');
    Route::delete('/invite-codes/{inviteCode}', [AdminInviteCodeController::class, 'destroy'])->name('admin.invite-codes.destroy');
});
