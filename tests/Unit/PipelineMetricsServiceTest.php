<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\PipelineFailureReason;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Pipeline\PipelineMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

/** @param array<string, mixed> $attributes */
function metricJob(array $attributes): DataProcessingJob
{
    return DataProcessingJob::factory()->make($attributes);
}

test('it aggregates counts, success rate, durations and failure reasons', function () {
    Cache::flush();

    $start = Carbon::parse('2026-05-01 10:00:00');

    $finished = new Collection([
        metricJob([
            'status' => DataProcessingJobStatus::COMPLETED,
            'type' => DataProcessingJobType::EXPORT,
            'started_at' => $start,
            'completed_at' => $start->copy()->addSeconds(1),
            'success_count' => 100,
        ]),
        metricJob([
            'status' => DataProcessingJobStatus::COMPLETED,
            'type' => DataProcessingJobType::EXPORT,
            'started_at' => $start,
            'completed_at' => $start->copy()->addSeconds(2),
            'success_count' => 50,
        ]),
        metricJob([
            'status' => DataProcessingJobStatus::COMPLETED,
            'type' => DataProcessingJobType::EXPORT,
            'started_at' => $start,
            'completed_at' => $start->copy()->addSeconds(3),
            'success_count' => 10,
        ]),
        metricJob([
            'status' => DataProcessingJobStatus::FAILED,
            'type' => DataProcessingJobType::IMPORT,
            'failure_reason' => PipelineFailureReason::TIMEOUT,
        ]),
    ]);

    $repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);
    $repository->shouldReceive('statusCounts')->andReturn([
        'pending' => 2,
        'processing' => 1,
        'completed' => 10,
        'failed' => 4,
        'cancelled' => 0,
        'total' => 17,
    ]);
    $repository->shouldReceive('finishedRunsSince')->andReturn($finished);
    $repository->shouldReceive('runsCreatedSince')->andReturn(5);
    $repository->shouldReceive('failedRecentCount')->andReturn(1);
    $repository->shouldReceive('staleProcessingBefore')->andReturn(new Collection([
        metricJob(['status' => DataProcessingJobStatus::PROCESSING]),
        metricJob(['status' => DataProcessingJobStatus::PROCESSING]),
    ]));

    $metrics = (new PipelineMetricsService($repository))->snapshot();

    expect($metrics['active_now'])->toBe(3)
        ->and($metrics['queued'])->toBe(2)
        ->and($metrics['runs_today'])->toBe(5)
        ->and($metrics['completed_7d'])->toBe(3)
        ->and($metrics['failed_7d'])->toBe(1)
        ->and($metrics['success_rate_7d'])->toBe(75.0)
        ->and($metrics['avg_duration_ms'])->toBe(2000)
        ->and($metrics['p95_duration_ms'])->toBe(3000)
        ->and($metrics['records_7d'])->toBe(160)
        ->and($metrics['failures_24h'])->toBe(1)
        ->and($metrics['stale'])->toBe(2)
        ->and($metrics['failure_reasons'])->toBe([
            ['reason' => 'timeout', 'label' => PipelineFailureReason::TIMEOUT->label(), 'count' => 1],
        ]);
});

test('it reports a null success rate when nothing finished', function () {
    Cache::flush();

    $repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);
    $repository->shouldReceive('statusCounts')->andReturn([
        'pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0, 'total' => 0,
    ]);
    $repository->shouldReceive('finishedRunsSince')->andReturn(new Collection);
    $repository->shouldReceive('runsCreatedSince')->andReturn(0);
    $repository->shouldReceive('failedRecentCount')->andReturn(0);
    $repository->shouldReceive('staleProcessingBefore')->andReturn(new Collection);

    $metrics = (new PipelineMetricsService($repository))->snapshot();

    expect($metrics['success_rate_7d'])->toBeNull()
        ->and($metrics['avg_duration_ms'])->toBeNull()
        ->and($metrics['p95_duration_ms'])->toBeNull()
        ->and($metrics['failure_reasons'])->toBe([]);
});
