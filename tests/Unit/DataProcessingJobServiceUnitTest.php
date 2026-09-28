<?php

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\AuditEvent;
use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Enums\NotificationType;
use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Notification\NotificationService;
use App\Services\Pipeline\PipelineEventRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        'completed', 'failed', 'cancelled', 'retryScheduled',
    )->byDefault();
    $this->service = new DataProcessingJobService($this->repository, $this->notifications, $this->audit, $this->pipeline);
});

afterEach(function () {
    Mockery::close();
});

test('paginate calls the repository with the filter page size', function () {
    $filters = new DataProcessingJobFilterDTO(perPage: 25);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')
        ->once()
        ->with($filters, 25)
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});

test('createExport persists a pending export and dispatches the job', function () {
    Queue::fake();

    $dto = new DataProcessingJobDTO(
        entityType: DataEntity::USERS,
        format: ExportFormat::XLSX,
        filters: ['search' => 'ada'],
    );

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['type'] === 'export'
            && $data['entity_type'] === 'users'
            && $data['format'] === 'xlsx'
            && $data['filters'] === ['search' => 'ada']))
        ->andReturn(new DataProcessingJob([
            'job_id' => 'job-1',
            'type' => 'export',
            'status' => 'pending',
            'entity_type' => 'users',
            'format' => 'xlsx',
        ]));

    $job = $this->service->createExport($dto);

    expect($job)->toBeInstanceOf(DataProcessingJob::class);

    Queue::assertPushed(ProcessExport::class);
});

test('markFailed records the failure state and error message', function () {
    $job = DataProcessingJob::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(function (DataProcessingJob $model, array $data) use ($job): bool {
            return $model->is($job)
                && $data['status'] === DataProcessingJobStatus::FAILED
                && $data['error_message'] === 'boom';
        })
        ->andReturn($job);

    $this->service->markFailed($job, 'boom');
});

test('resolveDownload returns null when the file is missing', function () {
    $job = DataProcessingJob::factory()->completed()->create([
        'file_path' => 'exports/missing.xlsx',
        'file_disk' => 'local',
    ]);

    expect($this->service->resolveDownload($job))->toBeNull();
});

test('markProgress records processed items and stage', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (DataProcessingJob $model, array $data): bool => $data['processed_items'] === 70
            && $data['stage'] === 'Importing rows')
        ->andReturn($job);

    $this->service->markProgress($job, 70, 'Importing rows');
});

test('cancel marks a pending job cancelled immediately', function () {
    $job = DataProcessingJob::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (DataProcessingJob $model, array $data): bool => $data['status'] === DataProcessingJobStatus::CANCELLED)
        ->andReturn($job);

    $this->service->cancel($job);
});

test('cancel requests cancellation for a processing job and audits it', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (DataProcessingJob $model, array $data): bool => array_key_exists('cancel_requested_at', $data))
        ->andReturn($job);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(function (AuditEvent $event, ?DataProcessingJob $subject) use ($job): bool {
            return $event === AuditEvent::PROCESSING_CANCELLED && $subject?->is($job);
        });

    $this->service->cancel($job);
});

test('retry re-queues a failed job', function () {
    Queue::fake();

    $job = DataProcessingJob::factory()->failed()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);

    $this->service->retry($job);

    Queue::assertPushed(ProcessExport::class);
});

test('duplicate queues a new job with the same parameters', function () {
    Queue::fake();

    $job = DataProcessingJob::factory()->create(['name' => 'Copy me']);

    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn (array $data): bool => $data['name'] === 'Copy me' && $data['entity_type'] === 'users')
        ->andReturn(new DataProcessingJob([
            'job_id' => 'job-2',
            'type' => 'export',
            'status' => 'pending',
            'entity_type' => 'users',
        ]));

    $this->service->duplicate($job);

    Queue::assertPushed(ProcessExport::class);
});

test('notifyFinished creates a notification for the owner', function () {
    $job = DataProcessingJob::factory()->completed()->create();

    $this->notifications->shouldReceive('create')
        ->once()
        ->withArgs(fn ($dto, int $createdBy): bool => $dto->type === NotificationType::EXPORT_COMPLETED
            && $createdBy === $job->user_id)
        ->andReturn(null);

    $this->service->notifyFinished($job);
});

test('statsFor delegates to the repository', function () {
    $this->repository->shouldReceive('statusCounts')
        ->once()
        ->with(null)
        ->andReturn(['total' => 0]);

    expect($this->service->statsFor(5, true))->toBe(['total' => 0]);
});

test('createExport records a dispatched pipeline event', function () {
    Queue::fake();

    $this->repository->shouldReceive('create')->once()->andReturn(new DataProcessingJob([
        'job_id' => 'job-9',
        'type' => 'export',
        'status' => 'pending',
        'entity_type' => 'users',
    ]));

    $this->pipeline->shouldReceive('dispatched')->once();

    $this->service->createExport(new DataProcessingJobDTO(entityType: DataEntity::USERS, format: ExportFormat::XLSX));
});

test('markProcessing records started only on the first transition', function () {
    $pending = DataProcessingJob::factory()->active()->create([
        'status' => DataProcessingJobStatus::PENDING,
        'started_at' => null,
    ]);

    $this->repository->shouldReceive('update')->once()->andReturn($pending);
    $this->pipeline->shouldReceive('started')->once();

    $this->service->markProcessing($pending, 10, 'Generating file');

    $processing = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($processing);

    $this->service->markProcessing($processing, 10, 'Generating file');
});

test('markProgress records a progress event with the total', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);

    $this->pipeline->shouldReceive('progress')
        ->once()
        ->with(Mockery::on(fn (DataProcessingJob $model): bool => $model->is($job)), 70, 100, 'Importing rows');

    $this->service->markProgress($job, 70, 'Importing rows');
});

test('attachArtifact records an artifact ready event', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('artifactReady')->once();

    $this->service->attachArtifact($job, 'users.xlsx', 'exports/users.xlsx', 'local', 10, 'text/csv');
});

test('markCompleted records a terminal completed event', function () {
    $job = DataProcessingJob::factory()->active()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('completed')->once();

    $this->service->markCompleted($job, 100, 100);
});

test('a terminal job does not record a second terminal event', function () {
    $job = DataProcessingJob::factory()->completed()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('completed')->never();

    $this->service->markCompleted($job, 1, 1);
});

test('retry records a retry scheduled event', function () {
    Queue::fake();

    $job = DataProcessingJob::factory()->failed()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($job);
    $this->pipeline->shouldReceive('retryScheduled')->once();

    $this->service->retry($job);
});
