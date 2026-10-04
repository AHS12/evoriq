<?php

use App\DTOs\Sync\UpsertCounts;
use App\Enums\SyncJobStatus;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeRunnerJob(array $attributes = []): ClockifySyncJob
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();
    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
    ]);

    return ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        ...$attributes,
    ]);
}

function bindRunnerHandler(SyncHandler $handler): void
{
    $registry = Mockery::mock(SyncHandlerRegistry::class);
    $registry->shouldReceive('resolve')->andReturn($handler);

    app()->instance(SyncHandlerRegistry::class, $registry);
}

test('a job resumes from the last completed page', function () {
    $job = makeRunnerJob(['page' => 1, 'page_size' => 2]);

    $handler = Mockery::mock(SyncHandler::class);
    $repository = Mockery::mock(SyncUpsertRepositoryInterface::class);

    $handler->shouldReceive('fetchPage')
        ->once()
        ->withArgs(fn ($context, int $page): bool => $page === 2)
        ->andReturn([['id' => 'e3']]);
    $handler->shouldReceive('map')->andReturnUsing(fn (array $raw): array => ['clockify_id' => $raw['id']]);
    $handler->shouldReceive('repository')->andReturn($repository);
    $repository->shouldReceive('upsertMany')->once()->andReturn(new UpsertCounts(created: 1));

    bindRunnerHandler($handler);

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect($job->page)->toBe(2)
        ->and($job->records_processed)->toBe(1)
        ->and($job->records_created)->toBe(1)
        ->and($job->status)->toBe(SyncJobStatus::COMPLETED);
});

test('counters accumulate across pages', function () {
    $job = makeRunnerJob(['page' => 0, 'page_size' => 2]);

    $handler = Mockery::mock(SyncHandler::class);
    $repository = Mockery::mock(SyncUpsertRepositoryInterface::class);

    $handler->shouldReceive('fetchPage')->andReturnUsing(
        fn ($context, int $page): array => $page === 1
            ? [['id' => 'e1'], ['id' => 'e2']]
            : [['id' => 'e3']],
    );
    $handler->shouldReceive('map')->andReturnUsing(fn (array $raw): array => ['clockify_id' => $raw['id']]);
    $handler->shouldReceive('repository')->andReturn($repository);
    $repository->shouldReceive('upsertMany')->andReturn(
        new UpsertCounts(created: 2),
        new UpsertCounts(created: 1),
    );

    bindRunnerHandler($handler);

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect($job->page)->toBe(2)
        ->and($job->records_processed)->toBe(3)
        ->and($job->records_created)->toBe(3)
        ->and($job->status)->toBe(SyncJobStatus::COMPLETED);
});

test('a page failure does not advance the checkpoint', function () {
    $job = makeRunnerJob(['page' => 0, 'page_size' => 2]);

    $handler = Mockery::mock(SyncHandler::class);
    $repository = Mockery::mock(SyncUpsertRepositoryInterface::class);

    $handler->shouldReceive('fetchPage')->andReturn([['id' => 'e1'], ['id' => 'e2']]);
    $handler->shouldReceive('map')->andReturnUsing(fn (array $raw): array => ['clockify_id' => $raw['id']]);
    $handler->shouldReceive('repository')->andReturn($repository);
    $repository->shouldReceive('upsertMany')->andThrow(new RuntimeException('boom'));

    bindRunnerHandler($handler);

    expect(fn () => app(SyncJobRunner::class)->run($job))->toThrow(RuntimeException::class);

    $job->refresh();

    expect($job->page)->toBe(0)
        ->and($job->records_processed)->toBe(0)
        ->and($job->records_created)->toBe(0)
        ->and($job->status)->toBe(SyncJobStatus::RUNNING);
});

test('cancellation stops the run between pages', function () {
    $job = makeRunnerJob(['page' => 0, 'page_size' => 2]);

    $handler = Mockery::mock(SyncHandler::class);
    $repository = Mockery::mock(SyncUpsertRepositoryInterface::class);

    $handler->shouldReceive('fetchPage')
        ->once()
        ->withArgs(fn ($context, int $page): bool => $page === 1)
        ->andReturnUsing(function () use ($job): array {
            ClockifySyncJob::query()->whereKey($job->id)->update(['status' => SyncJobStatus::CANCELLED->value]);

            return [['id' => 'e1'], ['id' => 'e2']];
        });
    $handler->shouldReceive('map')->andReturnUsing(fn (array $raw): array => ['clockify_id' => $raw['id']]);
    $handler->shouldReceive('repository')->andReturn($repository);
    $repository->shouldReceive('upsertMany')->once()->andReturn(new UpsertCounts(created: 2));

    bindRunnerHandler($handler);

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect($job->status)->toBe(SyncJobStatus::CANCELLED)
        ->and($job->page)->toBe(1);
});

test('an empty page completes the job', function () {
    $job = makeRunnerJob(['page' => 0]);

    $handler = Mockery::mock(SyncHandler::class);
    $handler->shouldReceive('fetchPage')->once()->andReturn([]);

    bindRunnerHandler($handler);

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect($job->status)->toBe(SyncJobStatus::COMPLETED)
        ->and($job->page)->toBe(0);
});
