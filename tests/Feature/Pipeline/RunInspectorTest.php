<?php

use App\Enums\PipelineRunType;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo([
        'data-processing.view',
        'data-processing.view.all',
        'data-processing.manage',
    ]);
});

test('the run resource groups seeded errors into issues', function () {
    $job = DataProcessingJob::factory()->failed()->create([
        'user_id' => $this->manager->id,
        'errors' => [
            ['row' => 1, 'type' => 'duplicate', 'message' => 'Duplicate email'],
            ['row' => 2, 'type' => 'duplicate', 'message' => 'Duplicate email'],
            ['row' => 3, 'type' => 'validation', 'message' => 'Bad value'],
        ],
    ]);

    $this->actingAs($this->manager)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('run.issues', 2)
            ->where('run.issues.0.type', 'duplicate')
            ->where('run.issues.0.count', 2)
            ->has('run.issues.0.sample_rows', 2));
});

test('the events endpoint paginates newest-first', function () {
    $job = DataProcessingJob::factory()->active()->create([
        'user_id' => $this->manager->id,
    ]);

    foreach (range(1, 5) as $sequence) {
        PipelineEvent::factory()->create([
            'run_type' => PipelineRunType::DATA_PROCESSING,
            'run_id' => (string) $job->id,
            'sequence' => $sequence,
            'occurred_at' => now()->addSeconds($sequence),
        ]);
    }

    $this->actingAs($this->manager)
        ->getJson(route('activity.events', ['dataProcessingJob' => $job, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.sequence', 5)
        ->assertJsonPath('data.1.sequence', 4);
});

test('the events endpoint forbids users who cannot view the run', function () {
    $job = DataProcessingJob::factory()->create(['user_id' => $this->manager->id]);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->getJson(route('activity.events', $job))
        ->assertForbidden();
});

test('advanced fields are gated to managers', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo('data-processing.view');

    $job = DataProcessingJob::factory()->completed()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('run.advanced', null));

    $this->actingAs($this->manager)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('run.advanced')
            ->has('run.advanced.raw.job_id')
            ->where('run.advanced.correlation_id', null));
});

test('an expired artifact is reported as non-downloadable', function () {
    $job = DataProcessingJob::factory()->completed()->create([
        'user_id' => $this->manager->id,
        'file_path' => 'exports/report.csv',
        'file_name' => 'report.csv',
        'completed_at' => now()->subDays(30),
    ]);

    $this->actingAs($this->manager)
        ->get(route('activity.show', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('run.artifacts.0.downloadable', false)
            ->where('run.artifacts.0.download_url', null));
});
