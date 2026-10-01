<?php

use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'data-processing.view',
        'data-processing.view.all',
    ]);
});

test('activity exposes the run contract for a processing job', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'user_id' => $this->user->id,
        'total_items' => 100,
        'processed_items' => 40,
        'last_heartbeat_at' => now(),
        'attempt' => 2,
    ]);

    PipelineEvent::factory()->create([
        'run_type' => PipelineRunType::DATA_PROCESSING,
        'run_id' => (string) $job->id,
        'sequence' => 1,
        'type' => PipelineEventType::STAGE_STARTED,
        'stage' => 'write',
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('data-processing/index')
            ->where('jobs.data.0.run_type', 'data_processing')
            ->where('jobs.data.0.attempts.current', 2)
            ->where('jobs.data.0.timing.stale', false)
            ->has('jobs.data.0.stages')
            ->has('jobs.data.0.progress.eta_seconds')
            ->has('jobs.data.0.counts.skipped')
            ->has('jobs.data.0.abilities')
            ->has('jobs.data.0.timeline'));
});

test('activity exposes a mapped failure reason for a failed job', function () {
    DataProcessingJob::factory()->failed()->create([
        'user_id' => $this->user->id,
        'error_message' => 'CLOCKIFY rate limit exceeded',
        'failure_reason' => null,
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('jobs.data.0.failure.reason', 'rate_limited')
            ->where('jobs.data.0.failure.label', 'Rate limited')
            ->where('jobs.data.0.failure.action', 'retry')
            ->has('jobs.data.0.failure.hint'));
});

test('the export endpoint returns the contract with a bounded timeline', function () {
    $job = DataProcessingJob::factory()->completed()->create([
        'user_id' => $this->user->id,
        'started_at' => now()->subSeconds(30),
        'completed_at' => now(),
    ]);

    PipelineEvent::factory()->create([
        'run_type' => PipelineRunType::DATA_PROCESSING,
        'run_id' => (string) $job->id,
        'sequence' => 1,
        'type' => PipelineEventType::DISPATCHED,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('exports.show', $job))
        ->assertOk()
        ->assertJsonPath('data.run_type', 'data_processing')
        ->assertJsonPath('data.timing.duration_ms', 30000)
        ->assertJsonCount(1, 'data.timeline')
        ->assertJsonPath('data.timeline.0.type', 'dispatched');
});

test('abilities expose resume for a failed import with an available source', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/imports/users.csv', 'Name,Email');

    DataProcessingJob::factory()->import()->failed()->create([
        'user_id' => $this->user->id,
        'input_disk' => 'local',
        'input_path' => 'exports/imports/users.csv',
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('jobs.data.0.abilities.resume', true)
            ->where('jobs.data.0.abilities.retry', true));
});

test('failure action becomes wait while a retry is pending', function () {
    DataProcessingJob::factory()->failed()->create([
        'user_id' => $this->user->id,
        'failure_reason' => null,
        'error_message' => 'CLOCKIFY rate limit exceeded',
        'next_retry_at' => now()->addMinutes(5),
    ]);

    $this->actingAs($this->user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('jobs.data.0.failure.action', 'wait'));
});
