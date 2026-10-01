<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\PipelineFailureReason;
use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use App\Jobs\RetryFailedDataProcessingJobs;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Notification\NotificationService;
use App\Services\Pipeline\FailureReasonResolver;
use App\Services\Pipeline\PipelineEventRecorder;
use App\Services\Pipeline\PipelineRunAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);
    $this->notifications = Mockery::mock(NotificationService::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();
    $this->pipeline = Mockery::mock(PipelineEventRecorder::class);
    $this->pipeline->shouldReceive(
        'dispatched', 'started', 'progress', 'artifactReady',
        'completed', 'failed', 'cancelled', 'retryScheduled', 'retryStarted', 'warning',
    )->byDefault();
    $this->runs = Mockery::mock(PipelineRunAggregator::class);
    $this->failures = Mockery::mock(FailureReasonResolver::class);
    $this->failures->shouldReceive('fromMessage')->andReturn(PipelineFailureReason::UNKNOWN)->byDefault();

    $this->service = new DataProcessingJobService(
        $this->repository,
        $this->notifications,
        $this->audit,
        $this->pipeline,
        $this->runs,
        $this->failures,
    );
});

afterEach(function () {
    Mockery::close();
});

test('retry rejects an active job', function () {
    $job = DataProcessingJob::factory()->active()->create();

    expect(fn () => $this->service->retry($job))->toThrow(RuntimeException::class);
});

test('retry resets a failed job and records a retry scheduled event', function () {
    Queue::fake();

    $job = DataProcessingJob::factory()->failed()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (DataProcessingJob $model, array $data): bool => $data['status'] === DataProcessingJobStatus::PENDING
            && $data['failure_reason'] === null
            && $data['completed_at'] === null)
        ->andReturn($job);

    $this->pipeline->shouldReceive('retryScheduled')->once();

    $this->service->retry($job);

    Queue::assertPushed(ProcessExport::class);
});

test('resume rejects a job without a resume strategy', function () {
    $job = DataProcessingJob::factory()->failed()->create();

    expect(fn () => $this->service->resume($job))->toThrow(RuntimeException::class);
});

test('resume re-queues a failed import whose source file exists', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::disk('local')->put('exports/imports/users.csv', 'Name,Email');

    $job = DataProcessingJob::factory()->import()->failed()->create([
        'input_disk' => 'local',
        'input_path' => 'exports/imports/users.csv',
    ]);

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('retryScheduled')->once();

    $this->service->resume($job);

    Queue::assertPushed(ProcessImport::class);
});

test('canResume reflects the model resume strategy', function () {
    Storage::fake('local');

    $export = DataProcessingJob::factory()->failed()->create();
    $import = DataProcessingJob::factory()->import()->failed()->create([
        'input_disk' => 'local',
        'input_path' => 'exports/imports/users.csv',
    ]);

    expect($this->service->canResume($export))->toBeFalse()
        ->and($this->service->canResume($import))->toBeFalse();

    Storage::disk('local')->put('exports/imports/users.csv', 'x');

    expect($this->service->canResume($import->fresh()))->toBeTrue();
});

test('cancel emits a warning event for a processing job', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('warning')->once();

    $this->service->cancel($job);
});

test('markProcessing records a retry started event after a retry', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'status' => DataProcessingJobStatus::PENDING,
        'attempt' => 1,
    ]);

    $this->repository->shouldReceive('update')
        ->once()
        ->andReturnUsing(fn (DataProcessingJob $model, array $data): DataProcessingJob => $model->forceFill($data));

    $this->pipeline->shouldReceive('retryStarted')->once();
    $this->pipeline->shouldReceive('started')->never();

    $this->service->markProcessing($job, 10, 'Reading file');
});

test('retryFailed retries owned final runs and skips the rest', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = DataProcessingJob::factory()->failed()->create(['user_id' => $owner->id]);
    $alsoMine = DataProcessingJob::factory()->failed()->create(['user_id' => $owner->id]);
    $active = DataProcessingJob::factory()->active()->create(['user_id' => $owner->id]);
    $otherUser = DataProcessingJob::factory()->failed()->create(['user_id' => $other->id]);

    $this->repository->shouldReceive('findManyByIds')
        ->once()
        ->andReturn(new Collection([$mine, $alsoMine, $active, $otherUser]));

    $this->repository->shouldReceive('update')
        ->andReturnUsing(fn (DataProcessingJob $model, array $data): DataProcessingJob => $model->forceFill($data));

    $result = $this->service->retryFailed(
        [$mine->id, $alsoMine->id, $active->id, $otherUser->id],
        $owner->id,
        false,
    );

    expect($result)->toMatchArray(['queued' => 2, 'skipped' => 2, 'dispatched' => false]);

    Queue::assertPushed(ProcessExport::class, 2);
});

test('retryFailed hands large sets to the default queue', function () {
    Queue::fake();
    config(['pipeline.bulk_retry_threshold' => 1]);

    $owner = User::factory()->create();

    $a = DataProcessingJob::factory()->failed()->create(['user_id' => $owner->id]);
    $b = DataProcessingJob::factory()->failed()->create(['user_id' => $owner->id]);

    $this->repository->shouldReceive('findManyByIds')
        ->once()
        ->andReturn(new Collection([$a, $b]));

    $result = $this->service->retryFailed([$a->id, $b->id], $owner->id, false);

    expect($result)->toMatchArray(['queued' => 2, 'dispatched' => true]);

    Queue::assertPushed(RetryFailedDataProcessingJobs::class);
});
