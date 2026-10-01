<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\PipelineEventType;
use App\Jobs\ProcessImport;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'data-processing.view',
        'data-processing.manage',
        'data-processing.delete',
    ]);
});

test('retry re-queues a failed run and records a retry scheduled event', function () {
    $job = DataProcessingJob::factory()->failed()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.retry', $job))
        ->assertRedirect();

    expect($job->fresh()->status)->toBe(DataProcessingJobStatus::PENDING);

    expect(
        PipelineEvent::query()
            ->where('run_id', (string) $job->id)
            ->where('type', PipelineEventType::RETRY_SCHEDULED->value)
            ->exists(),
    )->toBeTrue();
});

test('retry is rejected for an active run', function () {
    $job = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.retry', $job))
        ->assertRedirect();

    expect($job->fresh()->status)->toBe(DataProcessingJobStatus::PROCESSING);
});

test('resume re-queues a failed import when the source still exists', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/imports/users.csv', 'Name,Email');

    $job = DataProcessingJob::factory()->import()->failed()->create([
        'user_id' => $this->user->id,
        'input_disk' => 'local',
        'input_path' => 'exports/imports/users.csv',
    ]);

    $this->actingAs($this->user)
        ->post(route('activity.resume', $job))
        ->assertRedirect();

    expect($job->fresh()->status)->toBe(DataProcessingJobStatus::PENDING);

    Queue::assertPushed(ProcessImport::class);
});

test('resume is rejected when the run has no resume strategy', function () {
    $job = DataProcessingJob::factory()->failed()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.resume', $job))
        ->assertRedirect();

    expect($job->fresh()->status)->toBe(DataProcessingJobStatus::FAILED);
});

test('bulk retry queues only owned final runs and reports counts', function () {
    $mine = DataProcessingJob::factory()->failed()->create(['user_id' => $this->user->id]);
    $alsoMine = DataProcessingJob::factory()->cancelled()->create(['user_id' => $this->user->id]);
    $active = DataProcessingJob::factory()->active()->create(['user_id' => $this->user->id]);
    $other = DataProcessingJob::factory()->failed()->create();

    $this->actingAs($this->user)
        ->post(route('activity.retryFailed'), [
            'ids' => [$mine->id, $alsoMine->id, $active->id, $other->id],
        ])
        ->assertRedirect();

    expect($mine->fresh()->status)->toBe(DataProcessingJobStatus::PENDING)
        ->and($alsoMine->fresh()->status)->toBe(DataProcessingJobStatus::PENDING)
        ->and($active->fresh()->status)->toBe(DataProcessingJobStatus::PROCESSING)
        ->and($other->fresh()->status)->toBe(DataProcessingJobStatus::FAILED);
});

test('retrying an import reuses its row and queues it once', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/imports/users.csv', 'Name,Email');

    $job = DataProcessingJob::factory()->import()->failed()->create([
        'user_id' => $this->user->id,
        'input_disk' => 'local',
        'input_path' => 'exports/imports/users.csv',
    ]);

    $this->actingAs($this->user)->post(route('activity.retry', $job))->assertRedirect();
    // The run is now active, so a second retry is rejected rather than creating
    // a duplicate.
    $this->actingAs($this->user)->post(route('activity.retry', $job))->assertRedirect();

    expect(DataProcessingJob::query()->count())->toBe(1);

    Queue::assertPushed(ProcessImport::class, 1);
});
