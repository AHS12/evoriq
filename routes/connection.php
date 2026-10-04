<?php

use App\Http\Controllers\Connection\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('connections')->name('connections.')->group(function () {
    Route::get('/', [ConnectionController::class, 'index'])->name('index');
    Route::post('verify', [ConnectionController::class, 'verify'])->name('verify');
    Route::post('/', [ConnectionController::class, 'store'])->name('store');

    Route::put('{connection}', [ConnectionController::class, 'update'])->name('update');
    Route::post('{connection}/verify', [ConnectionController::class, 'reverify'])->name('reverify');
    Route::post('{connection}/key', [ConnectionController::class, 'rotateKey'])->name('rotate-key');
    Route::post('{connection}/disable', [ConnectionController::class, 'disable'])->name('disable');
    Route::post('{connection}/enable', [ConnectionController::class, 'enable'])->name('enable');
    Route::put('{connection}/workspace', [ConnectionController::class, 'selectWorkspace'])->name('workspace.update');
});
