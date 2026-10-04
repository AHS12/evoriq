<?php

use App\Enums\SyncJobStatus;
use App\Enums\SyncPriority;
use App\Jobs\Sync\ResumeSyncRunJob;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\SyncRunService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['clockify.sync_concurrency' => 10]);
});

/**
 * @return array{0: ClockifyConnection, 1: ClockifyWorkspace}
 */
function backpressureWorkspace(): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    return [$connection, $workspace];
}

function pendingRun(ClockifyConnection $connection, ClockifyWorkspace $workspace, SyncPriority $priority): ClockifySyncRun
{
    $run = ClockifySyncRun::factory()->running()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'priority' => $priority,
    ]);

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'workspace_id' => $workspace->id,
        'status' => SyncJobStatus::PENDING,
    ]);

    return $run;
}

test('a low-priority run defers while a higher-priority run is active', function () {
    Queue::fake();

    [$connection, $workspace] = backpressureWorkspace();
    pendingRun($connection, $workspace, SyncPriority::HIGH);
    $low = pendingRun($connection, $workspace, SyncPriority::LOW);

    app(SyncRunService::class)->dispatchPending($low);

    Queue::assertNotPushed(SyncEntityJob::class);
    Queue::assertPushed(ResumeSyncRunJob::class);
});

test('a low-priority run defers when the window has no headroom', function () {
    Queue::fake();

    $connection = ClockifyConnection::factory()->free()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    ClockifyApiUsage::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'requests_used' => 25,
        'requests_remaining' => 5,
        'limit_requests' => 30,
    ]);

    $low = pendingRun($connection, $workspace, SyncPriority::LOW);

    app(SyncRunService::class)->dispatchPending($low);

    Queue::assertNotPushed(SyncEntityJob::class);
    Queue::assertPushed(ResumeSyncRunJob::class);
});

test('a low-priority run dispatches once nothing higher is pending and there is headroom', function () {
    Queue::fake();

    [$connection, $workspace] = backpressureWorkspace();
    $low = pendingRun($connection, $workspace, SyncPriority::LOW);

    app(SyncRunService::class)->dispatchPending($low);

    Queue::assertPushed(SyncEntityJob::class);
});

test('a high-priority run dispatches on the critical channel', function () {
    Queue::fake();

    [$connection, $workspace] = backpressureWorkspace();
    $high = pendingRun($connection, $workspace, SyncPriority::HIGH);

    app(SyncRunService::class)->dispatchPending($high);

    Queue::assertPushed(
        SyncEntityJob::class,
        fn (SyncEntityJob $job): bool => $job->queue === 'critical',
    );
});
