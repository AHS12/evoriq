<?php

use App\Http\Controllers\Export\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('exports')->name('exports.')->group(function () {
    Route::get('/', [ExportController::class, 'index'])->name('index');
    Route::post('/', [ExportController::class, 'store'])->name('store');
    Route::get('{dataProcessingJob}', [ExportController::class, 'show'])->name('show');
    Route::get('{dataProcessingJob}/download', [ExportController::class, 'download'])->name('download');
    Route::delete('{dataProcessingJob}', [ExportController::class, 'destroy'])->name('destroy');
});
