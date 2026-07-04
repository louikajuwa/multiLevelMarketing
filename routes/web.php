<?php

use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PartnerLevelController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

/*
 | Guest routes
 */
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendResetLink'])->name('password.email');

    Route::get('reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'resetPassword'])->name('password.update');
});

/*
 | Authenticated routes
 */
Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'changePassword'])->name('profile.password');

    /*
     | Admin area
     */
    Route::prefix('admin')->name('admin.')->group(function (): void {
        // Partner levels
        Route::get('partner-levels', [PartnerLevelController::class, 'index'])
            ->middleware('permission:partner_levels.view')->name('partner-levels.index');
        Route::post('partner-levels', [PartnerLevelController::class, 'store'])
            ->middleware('permission:partner_levels.create')->name('partner-levels.store');
        Route::put('partner-levels/{partner_level}', [PartnerLevelController::class, 'update'])
            ->middleware('permission:partner_levels.update')->name('partner-levels.update');
        Route::delete('partner-levels/{partner_level}', [PartnerLevelController::class, 'destroy'])
            ->middleware('permission:partner_levels.delete')->name('partner-levels.destroy');

        // Partners
        Route::get('partners', [PartnerController::class, 'index'])
            ->middleware('permission:partners.view')->name('partners.index');
        Route::post('partners', [PartnerController::class, 'store'])
            ->middleware('permission:partners.create')->name('partners.store');
        Route::put('partners/{partner}', [PartnerController::class, 'update'])
            ->middleware('permission:partners.update')->name('partners.update');
        Route::delete('partners/{partner}', [PartnerController::class, 'destroy'])
            ->middleware('permission:partners.delete')->name('partners.destroy');
    });
});
