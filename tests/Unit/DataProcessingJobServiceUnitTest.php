<?php

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportEntity;
use App\Enums\ExportFormat;
use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);
    $this->service = new DataProcessingJobService($this->repository);
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
        entityType: ExportEntity::USERS,
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
