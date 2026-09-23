<?php

use App\Enums\CommandRunStatus;
use App\Jobs\Developer\RunMaintenanceCommand;
use App\Models\CommandRun;
use App\Services\Developer\CommandRunService;
use Illuminate\Support\Facades\Artisan;

test('runs a maintenance command and records its output', function () {
    $run = CommandRun::factory()->create([
        'action' => 'view-clear',
        'command' => 'view:clear',
    ]);

    (new RunMaintenanceCommand($run))->handle(app(CommandRunService::class));

    $run->refresh();

    expect($run->status)->toBe(CommandRunStatus::COMPLETED)
        ->and($run->exit_code)->toBe(0)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->completed_at)->not->toBeNull();
});

test('marks the run failed when the command exits non-zero', function () {
    Artisan::shouldReceive('call')->once()->andReturn(1);

    $run = CommandRun::factory()->create([
        'action' => 'cache-clear',
        'command' => 'cache:clear',
    ]);

    (new RunMaintenanceCommand($run))->handle(app(CommandRunService::class));

    $run->refresh();

    expect($run->status)->toBe(CommandRunStatus::FAILED)
        ->and($run->exit_code)->toBe(1)
        ->and($run->error_message)->not->toBeNull();
});

test('marks the run failed when the command throws', function () {
    $run = CommandRun::factory()->create([
        'action' => 'cache-clear',
        'command' => 'not-a-real-command',
    ]);

    try {
        (new RunMaintenanceCommand($run))->handle(app(CommandRunService::class));
    } catch (Throwable) {
        // The job rethrows after recording the failure.
    }

    $run->refresh();

    expect($run->status)->toBe(CommandRunStatus::FAILED);
});
