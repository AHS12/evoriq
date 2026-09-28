<?php

use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'data-processing.view',
        'data-processing.view.all',
        'data-processing.manage',
    ]);
});

function makeRunEvent(DataProcessingJob $job, int $sequence, PipelineEventType $type = PipelineEventType::INFO): PipelineEvent
{
    return PipelineEvent::factory()->create([
        'run_type' => PipelineRunType::DATA_PROCESSING,
        'run_id' => (string) $job->id,
        'sequence' => $sequence,
        'type' => $type,
        'level' => $type->defaultLevel(),
        'occurred_at' => now()->addSeconds($sequence),
    ]);
}

test('renders the run timeline for the owner with a tail window', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'user_id' => $this->user->id,
        'total_items' => 100,
        'processed_items' => 40,
    ]);

    foreach (range(1, 5) as $sequence) {
        makeRunEvent($job, $sequence);
    }

    $this->actingAs($this->user)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('data-processing/show')
            ->where('run.id', $job->id)
            ->where('run.status', 'processing')
            ->has('events', 5)
            ->where('events.0.sequence', 1)
            ->where('events.4.sequence', 5)
            ->where('eventsMeta.oldest_sequence', 1)
            ->where('eventsMeta.latest_sequence', 5)
            ->where('eventsMeta.has_more_older', false)
            ->has('options'));
});

test('resolves a run by its job_id uuid as well as its numeric id', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.show', ['dataProcessingJob' => $job->job_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('data-processing/show')
            ->where('run.id', $job->id)
            ->where('run.job_id', $job->job_id));

    $this->actingAs($this->user)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('run.id', $job->id));
});

test('forbids users who cannot view the run', function () {
    $job = DataProcessingJob::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('activity.show', $job))
        ->assertForbidden();
});

test('after_sequence returns only newer events', function () {
    $job = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id]);

    foreach (range(1, 5) as $sequence) {
        makeRunEvent($job, $sequence);
    }

    $this->actingAs($this->user)
        ->get(route('activity.show', ['dataProcessingJob' => $job, 'after_sequence' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 3)
            ->where('events.0.sequence', 3)
            ->where('events.2.sequence', 5));
});

test('before_sequence returns older events and flags more', function () {
    config(['pipeline.timeline_page_size' => 2]);

    $job = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id]);

    foreach (range(1, 5) as $sequence) {
        makeRunEvent($job, $sequence);
    }

    $this->actingAs($this->user)
        ->get(route('activity.show', ['dataProcessingJob' => $job, 'before_sequence' => 4]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)
            ->where('events.0.sequence', 2)
            ->where('events.1.sequence', 3)
            ->where('eventsMeta.oldest_sequence', 2)
            ->where('eventsMeta.has_more_older', true));
});

test('partial reload returns only the timeline props', function () {
    $job = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id]);
    makeRunEvent($job, 1);

    $this->actingAs($this->user)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(
                'events,eventsMeta',
                fn (Assert $reload) => $reload
                    ->has('events')
                    ->has('eventsMeta')
                    ->missing('run')
                    ->missing('options'),
            ));
});

test('a failed run exposes its mapped failure and retry ability', function () {
    $job = DataProcessingJob::factory()->failed()->create([
        'user_id' => $this->user->id,
        'error_message' => 'CLOCKIFY rate limit exceeded',
        'failure_reason' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('run.status', 'failed')
            ->where('run.failure.reason', 'rate_limited')
            ->where('run.abilities.retry', true));
});

test('renders distinct retry events in the stream', function () {
    $job = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id, 'attempt' => 2]);

    makeRunEvent($job, 1, PipelineEventType::DISPATCHED);
    makeRunEvent($job, 2, PipelineEventType::RETRY_SCHEDULED);
    makeRunEvent($job, 3, PipelineEventType::RETRY_STARTED);

    $this->actingAs($this->user)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.1.type', 'retry_scheduled')
            ->where('events.2.type', 'retry_started'));
});
