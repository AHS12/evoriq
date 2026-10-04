<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\NotificationType;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Notification\NotificationService;
use App\Services\Pipeline\FailureReasonResolver;
use App\Services\Pipeline\PipelineEventRecorder;
use App\Services\Pipeline\PipelineRunAggregator;
use App\Services\Setting\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

function pnPreferences(bool $allows): NotificationPreferenceService
{
    $mock = Mockery::mock(NotificationPreferenceService::class);
    $mock->shouldReceive('allows')->andReturn($allows);

    return $mock;
}

function pnService(
    NotificationService $notifications,
    NotificationPreferenceService $preferences,
    ?DataProcessingJob $job = null,
): DataProcessingJobService {
    $repository = Mockery::mock(DataProcessingJobRepositoryInterface::class);

    if ($job !== null) {
        $repository->shouldReceive('update')->andReturn($job);
    }

    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('record')->byDefault();

    $pipeline = Mockery::mock(PipelineEventRecorder::class);
    $pipeline->shouldReceive(
        'dispatched', 'started', 'progress', 'artifactReady',
        'completed', 'failed', 'cancelled', 'retryScheduled', 'retryStarted', 'warning',
    )->byDefault();

    $pipelineEvents = Mockery::mock(PipelineEventRepositoryInterface::class);
    $pipelineEvents->shouldReceive('deleteForRun')->andReturn(0)->byDefault();

    return new DataProcessingJobService(
        $repository,
        $notifications,
        $audit,
        $pipeline,
        Mockery::mock(PipelineRunAggregator::class),
        Mockery::mock(FailureReasonResolver::class),
        $preferences,
        $pipelineEvents,
    );
}

test('queuing a long job notifies the owner with a deep link', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldReceive('create')
        ->once()
        ->withArgs(fn ($dto): bool => $dto->type === NotificationType::JOB_QUEUED
            && $dto->actionUrl !== null);

    $job = DataProcessingJob::factory()->create();

    pnService($notifications, pnPreferences(true))->notifyQueued($job);
});

test('retrying a job notifies the owner', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldReceive('create')
        ->once()
        ->withArgs(fn ($dto): bool => $dto->type === NotificationType::JOB_RETRYING);

    $job = DataProcessingJob::factory()->create();

    pnService($notifications, pnPreferences(true))->notifyRetrying($job);
});

test('a muted pipeline category suppresses lifecycle notifications', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldNotReceive('create');

    $job = DataProcessingJob::factory()->create();
    $service = pnService($notifications, pnPreferences(false));

    $service->notifyQueued($job);
    $service->notifyRetrying($job);
});

test('a muted pipeline category suppresses the finished notification', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldNotReceive('create');

    $job = DataProcessingJob::factory()->create([
        'status' => DataProcessingJobStatus::COMPLETED,
    ]);

    pnService($notifications, pnPreferences(false))->notifyFinished($job);
});

test('progress updates never create a notification', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldNotReceive('create');

    $job = DataProcessingJob::factory()->create([
        'status' => DataProcessingJobStatus::PROCESSING,
    ]);

    pnService($notifications, pnPreferences(true), $job)->markProgress($job, 42);
});
