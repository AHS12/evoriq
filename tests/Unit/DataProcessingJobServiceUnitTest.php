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
    $this->service = new DataProcessingJobService($this->repository, $this->notifications, $this->audit);
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
