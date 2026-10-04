<?php

use App\Enums\SyncJobStatus;
use App\Enums\SyncRunStatus;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: ClockifySyncRun, 1: ClockifyWorkspace}
 */
function resumableRun(SyncRunStatus $status = SyncRunStatus::RUNNING): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'status' => $status,
    ]);

    return [$run, $workspace];
}

test('sync:resume re-dispatches parked work for a non-final run', function () {
    Queue::fake();

    [$run, $workspace] = resumableRun();

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'workspace_id' => $workspace->id,
        'status' => SyncJobStatus::PENDING,
        'next_retry_at' => now()->subMinute(),
    ]);

    $this->artisan('sync:resume')->assertSuccessful();

    Queue::assertPushed(SyncEntityJob::class);
});

test('sync:resume ignores runs whose retries are not due yet', function () {
    Queue::fake();

    [$run, $workspace] = resumableRun();

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'workspace_id' => $workspace->id,
        'status' => SyncJobStatus::RETRY_SCHEDULED,
        'next_retry_at' => now()->addHour(),
    ]);

    $this->artisan('sync:resume')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('sync:resume ignores final runs', function () {
    Queue::fake();

    [$run, $workspace] = resumableRun(SyncRunStatus::COMPLETED);

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'workspace_id' => $workspace->id,
        'status' => SyncJobStatus::PENDING,
    ]);

    $this->artisan('sync:resume')->assertSuccessful();

    Queue::assertNothingPushed();
});
