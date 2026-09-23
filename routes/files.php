<?php

use App\Http\Controllers\File\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('files')->name('files.')->group(function () {
    Route::get('/', [FileController::class, 'index'])->name('index');
    Route::post('/', [FileController::class, 'store'])->name('store');
    Route::delete('{upload}', [FileController::class, 'destroy'])->name('destroy');
});
