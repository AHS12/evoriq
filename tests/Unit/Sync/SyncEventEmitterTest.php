<?php

use App\Enums\PipelineEventType;
use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\PipelineEvent;
use App\Services\Sync\SyncEventEmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function syncEmitterEvents(ClockifySyncRun $run): Collection
{
    return PipelineEvent::query()
        ->where('run_type', 'sync')
        ->where('run_id', (string) $run->id)
        ->orderBy('sequence')
        ->get();
}

test('a sync run lifecycle writes an ordered, typed event stream', function () {
    $run = ClockifySyncRun::factory()->running()->create();
    $job = ClockifySyncJob::factory()
        ->for($run, 'syncRun')
        ->reference(SyncEntityType::USER)
        ->create(['status' => SyncJobStatus::RUNNING, 'attempt' => 1]);

    $emitter = app(SyncEventEmitter::class);
    $emitter->runQueued($run->refresh());
    $emitter->runStarted($run);
    $emitter->jobStarted($job->refresh());
    $emitter->jobProgress($job->refresh()->forceFill(['records_processed' => 42]));
    $emitter->jobCompleted($job->refresh()->forceFill([
        'status' => SyncJobStatus::COMPLETED,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]));
    $emitter->runCompleted($run->refresh());

    $events = syncEmitterEvents($run);

    expect($events->pluck('type')->all())->toBe([
        PipelineEventType::DISPATCHED,
        PipelineEventType::STARTED,
        PipelineEventType::STAGE_STARTED,
        PipelineEventType::PROGRESS,
        PipelineEventType::STAGE_COMPLETED,
        PipelineEventType::COMPLETED,
    ]);

    $jobEvent = $events->firstWhere('type', PipelineEventType::STAGE_STARTED);
    expect($jobEvent->stage)->toBe('user')
        ->and($jobEvent->context['entity_type'])->toBe('USER')
        ->and($jobEvent->context['job_id'])->toBe($job->id);

    $progress = $events->firstWhere('type', PipelineEventType::PROGRESS);
    expect($progress->progress['processed'])->toBe(42)
        ->and($progress->stage)->toBe('user');
});

test('a run failure records a terminal failed event with its counts', function () {
    $run = ClockifySyncRun::factory()->running()->create([
        'records_created' => 10,
        'records_updated' => 2,
    ]);

    app(SyncEventEmitter::class)->runFailed($run->refresh(), 'Boom.');

    $event = syncEmitterEvents($run)->firstWhere('type', PipelineEventType::FAILED);

    expect($event)->not->toBeNull()
        ->and($event->message)->toBe('Boom.')
        ->and($event->context['created'])->toBe(10)
        ->and($event->level->value)->toBe('error');
});

test('an exhausted budget records a warning with the reset window', function () {
    $run = ClockifySyncRun::factory()->running()->create();

    app(SyncEventEmitter::class)->budgetExhausted($run->refresh(), 120);

    $event = syncEmitterEvents($run)->firstWhere('type', PipelineEventType::WARNING);

    expect($event)->not->toBeNull()
        ->and($event->context['resets_in'])->toBe(120)
        ->and($event->level->value)->toBe('warning');
});

test('a cancelled run records a single terminal cancelled event', function () {
    $run = ClockifySyncRun::factory()->running()->create();

    app(SyncEventEmitter::class)->runCancelled($run->refresh());

    $events = syncEmitterEvents($run);

    expect($events->pluck('type')->all())->toBe([PipelineEventType::CANCELLED]);
});
