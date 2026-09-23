<?php

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/setup.php';
require __DIR__.'/settings.php';
require __DIR__.'/exports.php';
require __DIR__.'/files.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/admin.php';
