<?php

use App\Http\Controllers\AuditLog\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('audit-logs')
    ->name('audit-logs.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::post('prune', [AuditLogController::class, 'prune'])->name('prune');
        Route::get('{audit_log}', [AuditLogController::class, 'show'])->name('show');
    });
