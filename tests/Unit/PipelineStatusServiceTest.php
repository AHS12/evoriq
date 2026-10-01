<?php

use App\DTOs\Pipeline\PipelineProgressDTO;
use App\DTOs\Pipeline\PipelineRunDTO;
use App\DTOs\Pipeline\PipelineTimingDTO;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Pipeline\PipelineRunAggregator;
use App\Services\Pipeline\PipelineStatusService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::store('array')->flush();
    config(['cache.default' => 'array', 'pipeline.status_cache_seconds' => 5]);

    $this->repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);
    $this->runs = Mockery::mock(PipelineRunAggregator::class);

    $this->runs->shouldReceive('aggregate')->andReturnUsing(
        fn (DataProcessingJob $job): PipelineRunDTO => new PipelineRunDTO(
            job: $job,
            progress: new PipelineProgressDTO(100, 40, 40, false, 10, 120, 30),
            timing: new PipelineTimingDTO(null, null, null, null, null, $job->isStale()),
            stages: [],
            timeline: new Collection,
            failureReason: null,
            skipped: 0,
        ),
    );

    $this->service = new PipelineStatusService($this->repository, $this->runs);
});

afterEach(function () {
    Mockery::close();
});

function statusCounts(int $pending = 0, int $processing = 0): array
{
    return [
        'pending' => $pending,
        'processing' => $processing,
        'completed' => 0,
        'failed' => 0,
        'cancelled' => 0,
        'total' => $pending + $processing,
    ];
}

test('summarizes counts and the worst status', function () {
    $this->repository->shouldReceive('statusCounts')->once()->with(null)->andReturn(statusCounts(2, 1));
    $this->repository->shouldReceive('failedRecentCount')
        ->once()
        ->with(null, Mockery::type(CarbonInterface::class))
        ->andReturn(0);
    $this->repository->shouldReceive('activeJobs')
        ->once()
        ->with(null, 5)
        ->andReturn(new Collection([DataProcessingJob::factory()->active()->create()]));

    $summary = $this->service->summary(null, true);

    expect($summary['active_count'])->toBe(3)
        ->and($summary['queued_count'])->toBe(2)
        ->and($summary['processing_count'])->toBe(1)
        ->and($summary['failed_recent'])->toBe(0)
        ->and($summary['worst_status'])->toBe('info')
        ->and($summary['runs'])->toHaveCount(1)
        ->and($summary['freshness'])->toBeNull()
        ->and($summary['api_budget'])->toBeNull();
});

test('reports the error tone when there are recent failures', function () {
    $this->repository->shouldReceive('statusCounts')->once()->andReturn(statusCounts());
    $this->repository->shouldReceive('failedRecentCount')->once()->andReturn(2);
    $this->repository->shouldReceive('activeJobs')->once()->andReturn(new Collection);

    expect($this->service->summary(1, false)['worst_status'])->toBe('error');
});

test('reports the warning tone when a run is stale', function () {
    $stale = DataProcessingJob::factory()->active()->create([
        'last_heartbeat_at' => now()->subSeconds(4000),
    ]);

    $this->repository->shouldReceive('statusCounts')->once()->andReturn(statusCounts(0, 1));
    $this->repository->shouldReceive('failedRecentCount')->once()->andReturn(0);
    $this->repository->shouldReceive('activeJobs')->once()->andReturn(new Collection([$stale]));

    expect($this->service->summary(1, false)['worst_status'])->toBe('warning');
});

test('returns an idle summary when no runs are active', function () {
    $this->repository->shouldReceive('statusCounts')->once()->andReturn(statusCounts());
    $this->repository->shouldReceive('failedRecentCount')->once()->andReturn(0);
    $this->repository->shouldReceive('activeJobs')->once()->andReturn(new Collection);

    expect($this->service->summary(1, false)['worst_status'])->toBe('success');
});

test('caches the summary for the configured window', function () {
    $this->repository->shouldReceive('statusCounts')->once()->andReturn(statusCounts(1));
    $this->repository->shouldReceive('failedRecentCount')->once()->andReturn(0);
    $this->repository->shouldReceive('activeJobs')->once()->andReturn(new Collection);

    $first = $this->service->summary(1, false);
    $second = $this->service->summary(1, false);

    expect($second)->toBe($first);
});

test('returns an empty summary without pipeline access', function () {
    $this->repository->shouldReceive('statusCounts')->never();

    expect($this->service->summary(null, false))->toMatchArray([
        'active_count' => 0,
        'worst_status' => 'success',
        'runs' => [],
    ]);
});
