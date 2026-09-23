<?php

use App\Http\Controllers\User\InvitationController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::patch('{user}', [UserController::class, 'update'])->name('update');
    Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('{user}/roles', [UserController::class, 'assignRoles'])->name('roles');
    Route::post('{user}/invite', [UserController::class, 'resendInvitation'])->name('invite');
    Route::post('{user}/suspend', [UserController::class, 'toggleStatus'])->name('suspend');
    Route::post('{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('password-reset');
});

Route::middleware('guest')->prefix('invitations')->name('invitations.')->group(function () {
    Route::get('{user}/accept', [InvitationController::class, 'show'])
        ->middleware('signed')
        ->name('accept');

    Route::post('{user}/accept', [InvitationController::class, 'store'])
        ->middleware('signed')
        ->name('store');
});
