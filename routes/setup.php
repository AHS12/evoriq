<?php

use App\Http\Controllers\Setup\SetupController;
use App\Http\Middleware\EnsureSetupIncomplete;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureSetupIncomplete::class)
    ->prefix('setup')
    ->name('setup.')
    ->group(function () {
        Route::get('/', [SetupController::class, 'show'])->name('index');
        Route::post('environment', [SetupController::class, 'generateKey'])->name('environment');
        Route::post('database/test', [SetupController::class, 'testDatabase'])->name('database.test');
        Route::post('database', [SetupController::class, 'storeDatabase'])->name('database');
        Route::post('drivers', [SetupController::class, 'storeDrivers'])->name('drivers');
        Route::post('migrate', [SetupController::class, 'migrate'])->name('migrate');
        Route::post('/', [SetupController::class, 'store'])->name('store');
    });
