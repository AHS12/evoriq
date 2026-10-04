<?php

use App\DTOs\Sync\ImportPlan;
use App\DTOs\Sync\ImportPlanEstimate;
use App\DTOs\Sync\ImportPlanJob;
use App\DTOs\Sync\ImportPlanPhase;
use App\Enums\ApiUsageWindowType;
use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncMode;
use App\Enums\SyncPhase;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Events\Sync\SyncRunFinished;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\SyncRunService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function syncRunPlan(int $factJobs = 1): ImportPlan
{
    $phases = [
        new ImportPlanPhase(SyncPhase::REFERENCE, [
            new ImportPlanJob(SyncEntityType::USER, SyncPhase::REFERENCE, SyncPriority::NORMAL),
        ]),
    ];

    if ($factJobs > 0) {
        $jobs = [];

        for ($i = 1; $i <= $factJobs; $i++) {
            $jobs[] = new ImportPlanJob(
                SyncEntityType::TIME_ENTRY,
                SyncPhase::FACT,
                SyncPriority::NORMAL,
                CarbonImmutable::parse('2026-05-01'),
                CarbonImmutable::parse('2026-06-01'),
                "u{$i}",
            );
        }

        $phases[] = new ImportPlanPhase(SyncPhase::FACT, $jobs);
    }

    return new ImportPlan(
        mode: SyncMode::INITIAL,
        priority: SyncPriority::NORMAL,
        rangeStart: CarbonImmutable::parse('2026-05-01'),
        rangeEnd: CarbonImmutable::parse('2026-06-01'),
        pageSize: 200,
        phases: $phases,
        estimate: new ImportPlanEstimate(2, 1, 2, ApiUsageWindowType::HOUR, 30, 3600, 3600),
    );
}

/**
 * @return array{0: ClockifyConnection, 1: ClockifyWorkspace}
 */
function syncRunWorkspace(): array
{
    $connection = ClockifyConnection::factory()->free()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    return [$connection, $workspace];
}

test('starting a plan creates a run with the planned jobs and dispatches a wave', function () {
    Queue::fake();
    [$connection, $workspace] = syncRunWorkspace();

    $run = app(SyncRunService::class)->startFromPlan(syncRunPlan(2), $connection, $workspace, SyncTrigger::INITIAL_IMPORT);

    expect($run->total_jobs)->toBe(3)
        ->and($run->jobs()->count())->toBe(3)
        ->and($run->status)->toBe(SyncRunStatus::RUNNING);

    // Default concurrency is 2, so only two jobs go out in the first wave.
    Queue::assertPushed(SyncEntityJob::class, 2);
});

test('the run finalizes when every job is terminal', function () {
    Queue::fake();
    Event::fake([SyncRunFinished::class]);
    [$connection, $workspace] = syncRunWorkspace();

    $service = app(SyncRunService::class);
    $run = $service->startFromPlan(syncRunPlan(1), $connection, $workspace);

    $run->jobs()->update(['status' => SyncJobStatus::COMPLETED->value]);

    $service->onJobFinished($run->jobs()->first());

    $run->refresh();

    expect($run->status)->toBe(SyncRunStatus::COMPLETED)
        ->and($run->completed_jobs)->toBe(2)
        ->and($run->completed_at)->not->toBeNull();

    Event::assertDispatched(SyncRunFinished::class);
});

test('a run fails when no job completed', function () {
    Queue::fake();
    [$connection, $workspace] = syncRunWorkspace();

    $service = app(SyncRunService::class);
    $run = $service->startFromPlan(syncRunPlan(1), $connection, $workspace);

    $run->jobs()->update(['status' => SyncJobStatus::FAILED->value]);

    $service->onJobFinished($run->jobs()->first());

    expect($run->fresh()->status)->toBe(SyncRunStatus::FAILED);
});

test('cancelling a run cancels its non-final jobs', function () {
    Queue::fake();
    Event::fake([SyncRunFinished::class]);
    [$connection, $workspace] = syncRunWorkspace();

    $service = app(SyncRunService::class);
    $run = $service->startFromPlan(syncRunPlan(1), $connection, $workspace);

    $service->cancel($run);

    $run->refresh();

    expect($run->status)->toBe(SyncRunStatus::CANCELLED)
        ->and($run->jobs()->where('status', SyncJobStatus::CANCELLED->value)->count())->toBe(2)
        ->and($run->jobs()->where('status', SyncJobStatus::PENDING->value)->count())->toBe(0);
});

test('resume re-dispatches pending jobs', function () {
    Queue::fake();
    [$connection, $workspace] = syncRunWorkspace();

    $service = app(SyncRunService::class);
    $run = $service->startFromPlan(syncRunPlan(1), $connection, $workspace);

    Queue::fake();

    $service->resume($run);

    Queue::assertPushed(SyncEntityJob::class);
});
