<?php

use App\Enums\SyncJobStatus;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'clockify.sync_job.max_attempts' => 3,
        'clockify.sync_job.stale_after' => 900,
        'clockify.sync_job.retry_base_seconds' => 10,
        'clockify.sync_job.retry_max_seconds' => 60,
    ]);
});

/**
 * @return array{0: ClockifySyncRun, 1: ClockifySyncJob}
 */
function staleSyncJob(array $attributes = []): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();
    $run = ClockifySyncRun::factory()->running()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
    ]);

    $job = ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'status' => SyncJobStatus::RUNNING,
        'attempt' => 1,
        'started_at' => now()->subHour(),
        'heartbeat_at' => now()->subMinutes(30),
        ...$attributes,
    ]);

    return [$run, $job];
}

test('a stale running job is rescheduled and its job requeued', function () {
    Queue::fake();

    [, $job] = staleSyncJob();

    $this->artisan('sync:reap-stale')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe(SyncJobStatus::RETRY_SCHEDULED)
        ->and($job->next_retry_at)->not->toBeNull();

    Queue::assertPushed(SyncEntityJob::class);
});

test('a stale job that exhausted its attempts fails and finalizes the run', function () {
    Queue::fake();

    [$run, $job] = staleSyncJob(['attempt' => 3]);

    $this->artisan('sync:reap-stale')->assertSuccessful();

    $job->refresh();
    $run->refresh();

    expect($job->status)->toBe(SyncJobStatus::FAILED)
        ->and($job->completed_at)->not->toBeNull()
        ->and($run->status->isFinal())->toBeTrue();
});

test('a fresh running job is left untouched', function () {
    Queue::fake();

    [, $job] = staleSyncJob(['heartbeat_at' => now()]);

    $this->artisan('sync:reap-stale')->assertSuccessful();

    expect($job->refresh()->status)->toBe(SyncJobStatus::RUNNING);

    Queue::assertNothingPushed();
});
