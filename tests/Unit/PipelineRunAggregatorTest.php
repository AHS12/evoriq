<?php

use App\Enums\PipelineFailureReason;
use App\Enums\PipelineRunType;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use App\Services\Pipeline\FailureReasonResolver;
use App\Services\Pipeline\PipelineRunAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function pipelineEvent(array $attributes): PipelineEvent
{
    return new PipelineEvent(array_merge([
        'run_type' => 'data_processing',
        'run_id' => '1',
        'sequence' => 1,
        'type' => 'info',
        'level' => 'info',
        'attempt' => 1,
        'occurred_at' => now(),
    ], $attributes));
}

beforeEach(function () {
    $this->repository = Mockery::mock(PipelineEventRepositoryInterface::class);
    $this->aggregator = new PipelineRunAggregator($this->repository, new FailureReasonResolver);
});

afterEach(function () {
    Mockery::close();
});

test('derives ordered stages with completed and running status', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'total_items' => 100,
        'processed_items' => 80,
        'last_heartbeat_at' => now(),
    ]);

    $base = now()->subMinutes(5);
    $events = new Collection([
        pipelineEvent(['sequence' => 1, 'type' => 'stage_started', 'stage' => 'prepare', 'occurred_at' => $base]),
        pipelineEvent(['sequence' => 2, 'type' => 'stage_completed', 'stage' => 'prepare', 'duration_ms' => 1000, 'occurred_at' => $base->copy()->addSecond()]),
        pipelineEvent(['sequence' => 3, 'type' => 'stage_started', 'stage' => 'generate', 'occurred_at' => $base->copy()->addSeconds(2)]),
        pipelineEvent([
            'sequence' => 4,
            'type' => 'progress',
            'stage' => 'generate',
            'progress' => ['total' => 100, 'processed' => 80, 'percentage' => 80.0],
            'occurred_at' => $base->copy()->addSeconds(3),
        ]),
    ]);

    $this->repository->shouldReceive('allForRun')->once()->andReturn($events);

    $run = $this->aggregator->aggregate($job);

    expect($run->stages)->toHaveCount(2)
        ->and($run->stages[0]->key)->toBe('prepare')
        ->and($run->stages[0]->label)->toBe('Prepare')
        ->and($run->stages[0]->status)->toBe('completed')
        ->and($run->stages[0]->durationMs)->toBe(1000)
        ->and($run->stages[1]->key)->toBe('generate')
        ->and($run->stages[1]->status)->toBe('running')
        ->and($run->stages[1]->processed)->toBe(80)
        ->and($run->stages[1]->total)->toBe(100);
});

test('returns a null eta when there are too few progress points', function () {
    $job = DataProcessingJob::factory()->active()->create(['total_items' => 1000, 'processed_items' => 200]);

    $this->repository->shouldReceive('allForRun')->once()->andReturn(new Collection([
        pipelineEvent(['sequence' => 1, 'type' => 'progress', 'stage' => 'write', 'progress' => ['total' => 1000, 'processed' => 200, 'percentage' => 20.0]]),
    ]));

    expect($this->aggregator->aggregate($job)->progress->etaSeconds)->toBeNull();
});

test('estimates the eta from the current stage throughput', function () {
    $job = DataProcessingJob::factory()->active()->create(['total_items' => 1000, 'processed_items' => 400]);

    $base = now()->subMinutes(3);
    $this->repository->shouldReceive('allForRun')->once()->andReturn(new Collection([
        pipelineEvent(['sequence' => 1, 'type' => 'stage_started', 'stage' => 'write', 'occurred_at' => $base]),
        pipelineEvent(['sequence' => 2, 'type' => 'progress', 'stage' => 'write', 'progress' => ['total' => 1000, 'processed' => 200, 'percentage' => 20.0], 'occurred_at' => $base->copy()->addSeconds(20)]),
        pipelineEvent(['sequence' => 3, 'type' => 'progress', 'stage' => 'write', 'progress' => ['total' => 1000, 'processed' => 400, 'percentage' => 40.0], 'occurred_at' => $base->copy()->addSeconds(80)]),
    ]));

    // 200 rows in 60s = 3.33/s; 600 remaining ≈ 180s.
    expect($this->aggregator->aggregate($job)->progress->etaSeconds)->toBe(180);
});

test('computes throughput per minute and marks a stale run', function () {
    config(['pipeline.stale_after' => 100]);

    $stale = DataProcessingJob::factory()->active()->create([
        'total_items' => 1000,
        'processed_items' => 400,
        'started_at' => now()->subSeconds(200),
        'last_heartbeat_at' => now()->subSeconds(200),
    ]);

    $this->repository->shouldReceive('allForRun')->andReturn(new Collection);

    $run = $this->aggregator->aggregate($stale);

    expect($run->progress->throughputPerMin)->toBe(120)
        ->and($run->progress->elapsedSeconds)->toBe(200)
        ->and($run->timing->stale)->toBeTrue();
});

test('classifies a failed run from its stored message', function () {
    $job = DataProcessingJob::factory()->failed()->create([
        'error_message' => 'Too many requests. Please try again later.',
        'failure_reason' => null,
    ]);

    $this->repository->shouldReceive('allForRun')->once()->andReturn(new Collection);

    $run = $this->aggregator->aggregate($job);

    expect($run->failureReason)->toBe(PipelineFailureReason::RATE_LIMITED);
});

test('embeds a bounded timeline only when requested', function () {
    config(['pipeline.timeline_limit' => 2]);

    $job = DataProcessingJob::factory()->create();

    $events = new Collection([
        pipelineEvent(['sequence' => 1, 'type' => 'dispatched']),
        pipelineEvent(['sequence' => 2, 'type' => 'started']),
        pipelineEvent(['sequence' => 3, 'type' => 'info']),
    ]);

    $this->repository->shouldReceive('allForRun')->andReturn($events);

    expect($this->aggregator->aggregate($job)->timeline)->toHaveCount(0)
        ->and($this->aggregator->aggregate($job, true)->timeline)->toHaveCount(2)
        ->and($this->aggregator->aggregate($job, true)->timeline->last()->sequence)->toBe(3);
});

test('counts skipped rows from import issue types', function () {
    $job = DataProcessingJob::factory()->create([
        'errors' => [
            ['row' => 2, 'type' => 'duplicate', 'message' => 'exists'],
            ['row' => 3, 'type' => 'validation', 'message' => 'bad'],
            ['row' => 4, 'type' => 'duplicate', 'message' => 'exists'],
        ],
    ]);

    $this->repository->shouldReceive('allForRun')->once()->andReturn(new Collection);

    expect($this->aggregator->aggregate($job)->skipped)->toBe(2);
});

test('builds a tail window with older-page metadata', function () {
    config(['pipeline.timeline_page_size' => 3]);

    $job = DataProcessingJob::factory()->create();

    $events = new Collection([
        pipelineEvent(['sequence' => 8]),
        pipelineEvent(['sequence' => 9]),
        pipelineEvent(['sequence' => 10]),
    ]);

    $this->repository->shouldReceive('tailForRun')
        ->once()
        ->with(Mockery::type(PipelineRunType::class), Mockery::any(), 3)
        ->andReturn($events);
    $this->repository->shouldReceive('existsBefore')->once()->andReturn(true);

    $window = $this->aggregator->eventWindow($job);

    expect($window->oldestSequence)->toBe(8)
        ->and($window->latestSequence)->toBe(10)
        ->and($window->hasMoreOlder)->toBeTrue()
        ->and($window->meta()['has_more_older'])->toBeTrue()
        ->and($window->events)->toHaveCount(3);
});

test('builds an append window from after_sequence', function () {
    $job = DataProcessingJob::factory()->create();

    $events = new Collection([
        pipelineEvent(['sequence' => 6]),
        pipelineEvent(['sequence' => 7]),
    ]);

    $this->repository->shouldReceive('afterSequence')->once()->andReturn($events);

    $window = $this->aggregator->eventWindow($job, after: 5);

    expect($window->oldestSequence)->toBe(6)
        ->and($window->latestSequence)->toBe(7)
        ->and($window->hasMoreOlder)->toBeFalse();
});

test('builds a prepend window from before_sequence', function () {
    $job = DataProcessingJob::factory()->create();

    $events = new Collection([
        pipelineEvent(['sequence' => 1]),
        pipelineEvent(['sequence' => 2]),
    ]);

    $this->repository->shouldReceive('beforeSequence')->once()->andReturn($events);
    $this->repository->shouldReceive('existsBefore')
        ->once()
        ->with(Mockery::type(PipelineRunType::class), Mockery::any(), 1)
        ->andReturn(false);

    $window = $this->aggregator->eventWindow($job, before: 3);

    expect($window->oldestSequence)->toBe(1)
        ->and($window->latestSequence)->toBe(2)
        ->and($window->hasMoreOlder)->toBeFalse();
});

test('returns an empty window when a run has no events', function () {
    $job = DataProcessingJob::factory()->create();

    $this->repository->shouldReceive('tailForRun')->once()->andReturn(new Collection);

    $window = $this->aggregator->eventWindow($job);

    expect($window->events)->toHaveCount(0)
        ->and($window->oldestSequence)->toBeNull()
        ->and($window->latestSequence)->toBeNull()
        ->and($window->hasMoreOlder)->toBeFalse();
});

test('exposes dispatched and duration timing', function () {
    Carbon::setTestNow(now());

    $job = DataProcessingJob::factory()->completed()->create([
        'started_at' => now()->subSeconds(30),
        'completed_at' => now(),
    ]);

    $this->repository->shouldReceive('allForRun')->once()->andReturn(new Collection([
        pipelineEvent(['sequence' => 1, 'type' => 'dispatched', 'occurred_at' => now()->subMinutes(1)]),
    ]));

    $run = $this->aggregator->aggregate($job);

    expect($run->timing->durationMs)->toBe(30000)
        ->and($run->timing->dispatchedAt)->not->toBeNull();

    Carbon::setTestNow();
});
