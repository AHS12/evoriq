<?php

use App\Http\Controllers\DataProcessingJob\ActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('activity')->name('activity.')->group(function () {
    Route::get('/', [ActivityController::class, 'index'])->name('index');
    Route::post('export', [ActivityController::class, 'storeExport'])->name('storeExport');
    Route::post('import', [ActivityController::class, 'storeImport'])->name('storeImport');
    Route::get('import/template/{entity}', [ActivityController::class, 'template'])->name('template');

    Route::post('{dataProcessingJob}/cancel', [ActivityController::class, 'cancel'])->name('cancel');
    Route::post('{dataProcessingJob}/retry', [ActivityController::class, 'retry'])->name('retry');
    Route::post('{dataProcessingJob}/duplicate', [ActivityController::class, 'duplicate'])->name('duplicate');
    Route::delete('{dataProcessingJob}', [ActivityController::class, 'destroy'])->name('destroy');
});
