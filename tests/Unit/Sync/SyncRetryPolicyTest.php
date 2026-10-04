<?php

use App\Enums\SyncJobStatus;
use App\Models\ClockifySyncJob;
use App\Services\Sync\SyncRetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'clockify.sync_job.max_attempts' => 3,
        'clockify.sync_job.retry_base_seconds' => 10,
        'clockify.sync_job.retry_max_seconds' => 60,
    ]);
});

test('a transient failure schedules a backoff retry while attempts remain', function () {
    $job = ClockifySyncJob::factory()->create(['attempt' => 1]);

    $retried = app(SyncRetryPolicy::class)->retry($job, new RuntimeException('network blip'));

    $job->refresh();

    expect($retried)->toBeTrue()
        ->and($job->status)->toBe(SyncJobStatus::RETRY_SCHEDULED)
        ->and($job->last_error)->toBe('network blip')
        ->and($job->next_retry_at)->not->toBeNull()
        ->and($job->next_retry_at->isFuture())->toBeTrue();
});

test('a job that exhausts its attempts becomes terminally failed', function () {
    $job = ClockifySyncJob::factory()->create(['attempt' => 3]);

    $retried = app(SyncRetryPolicy::class)->retry($job, new RuntimeException('boom'));

    $job->refresh();

    expect($retried)->toBeFalse()
        ->and($job->status)->toBe(SyncJobStatus::FAILED)
        ->and($job->completed_at)->not->toBeNull();
});

test('backoff grows exponentially and is clamped to the ceiling', function () {
    $policy = app(SyncRetryPolicy::class);

    expect($policy->backoffSeconds(1))->toBeLessThanOrEqual(15)
        ->and($policy->backoffSeconds(2))->toBeLessThanOrEqual(25)
        ->and($policy->backoffSeconds(10))->toBeLessThanOrEqual(60);
});
