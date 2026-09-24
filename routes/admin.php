<?php

use App\Http\Controllers\Developer\DeveloperController;
use App\Http\Controllers\Setting\AppearanceController;
use App\Http\Controllers\Setting\MailSettingController;
use App\Http\Controllers\Setting\SettingController;
use App\Http\Middleware\EnsureDeveloperAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('general', [SettingController::class, 'edit'])
            ->middleware('can:settings.view')
            ->defaults('group', 'general')
            ->name('general.edit');

        Route::patch('general', [SettingController::class, 'update'])
            ->middleware('can:settings.update')
            ->defaults('group', 'general')
            ->name('general.update');

        Route::get('mail', [SettingController::class, 'edit'])
            ->middleware('can:settings.view')
            ->defaults('group', 'mail')
            ->name('mail.edit');

        Route::patch('mail', [SettingController::class, 'update'])
            ->middleware('can:settings.update')
            ->defaults('group', 'mail')
            ->name('mail.update');

        Route::post('mail/test', [MailSettingController::class, 'test'])
            ->middleware('can:settings.update')
            ->name('mail.test');

        Route::get('notifications', [SettingController::class, 'edit'])
            ->middleware('can:settings.view')
            ->defaults('group', 'notifications')
            ->name('notifications.edit');

        Route::patch('notifications', [SettingController::class, 'update'])
            ->middleware('can:settings.update')
            ->defaults('group', 'notifications')
            ->name('notifications.update');

        Route::get('appearance', [AppearanceController::class, 'edit'])
            ->middleware('can:settings.view')
            ->name('appearance.edit');

        Route::get('developer', [DeveloperController::class, 'index'])
            ->middleware(EnsureDeveloperAccess::class)
            ->name('developer.edit');

        Route::post('developer/maintenance/{action}', [DeveloperController::class, 'maintenance'])
            ->middleware(EnsureDeveloperAccess::class)
            ->name('developer.maintenance');

        Route::post('developer/maintenance-mode', [DeveloperController::class, 'maintenanceMode'])
            ->middleware(EnsureDeveloperAccess::class)
            ->name('developer.maintenance-mode');
    });
});
