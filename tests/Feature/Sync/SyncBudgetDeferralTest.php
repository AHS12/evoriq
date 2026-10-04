<?php

use App\Enums\SyncJobStatus;
use App\Exceptions\SyncBudgetExhausted;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;

afterEach(function () {
    Mockery::close();
});

test('a budget-exhausted job is parked without burning an attempt', function () {
    $connection = ClockifyConnection::factory()->free()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();
    $run = ClockifySyncRun::factory()->running()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
    ]);
    $job = ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'attempt' => 0,
        'page' => 3,
    ]);

    $handler = Mockery::mock(SyncHandler::class);
    $handler->shouldReceive('fetchPage')->once()->andThrow(new SyncBudgetExhausted(120));

    $registry = Mockery::mock(SyncHandlerRegistry::class);
    $registry->shouldReceive('resolve')->andReturn($handler);
    app()->instance(SyncHandlerRegistry::class, $registry);

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect($job->status)->toBe(SyncJobStatus::PENDING)
        ->and($job->attempt)->toBe(0)
        ->and($job->page)->toBe(3)
        ->and($job->next_retry_at)->not->toBeNull();
});
