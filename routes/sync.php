<?php

use App\Http\Controllers\Sync\ApiUsageController;
use App\Http\Controllers\Sync\ImportWizardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('api')->name('api-usage.')->group(function () {
        Route::get('usage', [ApiUsageController::class, 'show'])->name('show');
    });

    Route::prefix('import')->name('import.')->group(function () {
        Route::get('/', [ImportWizardController::class, 'index'])->name('index');
        Route::post('inspect', [ImportWizardController::class, 'inspect'])->name('inspect');
        Route::post('/', [ImportWizardController::class, 'store'])->name('store');
        Route::get('{syncRun}', [ImportWizardController::class, 'show'])->name('show');
    });
});
