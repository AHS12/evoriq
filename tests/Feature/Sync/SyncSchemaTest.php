<?php

use App\Enums\ApiUsageWindowType;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use App\Models\ClockifyRawRecord;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Models\Organization;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Support\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('the sync state tables exist', function () {
    expect(Schema::hasTable('clockify_sync_runs'))->toBeTrue()
        ->and(Schema::hasTable('clockify_sync_jobs'))->toBeTrue()
        ->and(Schema::hasTable('clockify_api_usage'))->toBeTrue()
        ->and(Schema::hasTable('clockify_entity_changes'))->toBeTrue()
        ->and(Schema::hasTable('clockify_deleted_entities'))->toBeTrue()
        ->and(Schema::hasTable('clockify_raw_records'))->toBeTrue();
});

test('a run holds many jobs and tracks a resumable page', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
    ]);

    $job = ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::TIME_ENTRY,
        'phase' => SyncPhase::FACT,
        'page' => 3,
        'checkpoint' => ['cursor' => 'abc'],
    ]);

    expect($run->jobs()->count())->toBe(1)
        ->and($job->syncRun->id)->toBe($run->id)
        ->and($job->workspace->id)->toBe($workspace->id)
        ->and($job->page)->toBe(3)
        ->and($job->checkpoint)->toBe(['cursor' => 'abc']);
});

test('api usage windows are unique per connection, workspace, type and start', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();
    $start = now()->startOfHour();

    $attributes = [
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'window_type' => ApiUsageWindowType::HOUR,
        'window_started_at' => $start,
    ];

    ClockifyApiUsage::factory()->create($attributes);

    expect(fn () => ClockifyApiUsage::factory()->create($attributes))
        ->toThrow(QueryException::class);
});

test('raw records are unique per entity identity', function () {
    $workspace = ClockifyWorkspace::factory()->create();

    $attributes = [
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::TIME_ENTRY,
        'clockify_id' => 'entry-1',
    ];

    ClockifyRawRecord::factory()->create($attributes);

    expect(fn () => ClockifyRawRecord::factory()->create($attributes))
        ->toThrow(QueryException::class);
});

test('sync tables are scoped to the current organization', function () {
    Organization::forgetDefault();
    app(OrganizationContext::class)->reset();

    $other = Organization::factory()->create();

    $mine = ClockifySyncRun::factory()->create();
    ClockifySyncRun::factory()->create(['organization_id' => $other->id]);

    expect(ClockifySyncRun::query()->pluck('id')->all())->toBe([$mine->id]);
});

test('the job repository advances pages and accumulates counters', function () {
    $job = ClockifySyncJob::factory()->create(['page' => 0]);

    $job = app(ClockifySyncJobRepositoryInterface::class)->advancePage(
        $job,
        2,
        ['processed' => 10, 'created' => 6, 'updated' => 4],
    );

    expect($job->page)->toBe(2)
        ->and($job->records_processed)->toBe(10)
        ->and($job->records_created)->toBe(6)
        ->and($job->records_updated)->toBe(4)
        ->and($job->heartbeat_at)->not->toBeNull();
});

test('the run repository recalculates counters from its jobs', function () {
    $run = ClockifySyncRun::factory()->create();

    ClockifySyncJob::factory()->for($run, 'syncRun')->completed()->create([
        'records_created' => 5,
        'records_updated' => 2,
    ]);
    ClockifySyncJob::factory()->for($run, 'syncRun')->create(['records_created' => 1]);

    $run = app(ClockifySyncRunRepositoryInterface::class)->countsRecalculate($run);

    expect($run->total_jobs)->toBe(2)
        ->and($run->completed_jobs)->toBe(1)
        ->and($run->records_created)->toBe(6)
        ->and($run->records_updated)->toBe(2);
});
