<?php

use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\SyncRunPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it derives entity stages with durations from the run jobs', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();
    $run = ClockifySyncRun::factory()->running()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'total_jobs' => 2,
        'completed_jobs' => 1,
    ]);

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'entity_type' => SyncEntityType::USER,
        'status' => SyncJobStatus::COMPLETED,
        'records_processed' => 10,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now()->subMinutes(4),
    ]);

    ClockifySyncJob::factory()->for($run, 'syncRun')->create([
        'entity_type' => SyncEntityType::PROJECTS,
        'status' => SyncJobStatus::RUNNING,
        'records_processed' => 3,
        'started_at' => now()->subMinute(),
    ]);

    $presented = app(SyncRunPresenter::class)->present($run->refresh());

    expect($presented['stages'])->toHaveCount(2)
        ->and($presented['stages'][0]['key'])->toBe('user')
        ->and($presented['stages'][0]['duration_ms'])->toBeInt()
        ->and($presented['stages'][1]['status'])->toBe('running')
        ->and($presented['progress']['total'])->toBe(2);
});
