<?php

use App\Http\Controllers\Connection\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('connections')->name('connections.')->group(function () {
    Route::get('/', [ConnectionController::class, 'index'])->name('index');
    Route::post('verify', [ConnectionController::class, 'verify'])->name('verify');
    Route::post('/', [ConnectionController::class, 'store'])->name('store');

    Route::post('{connection}/verify', [ConnectionController::class, 'reverify'])->name('reverify');
    Route::put('{connection}/workspace', [ConnectionController::class, 'selectWorkspace'])->name('workspace.update');
});
