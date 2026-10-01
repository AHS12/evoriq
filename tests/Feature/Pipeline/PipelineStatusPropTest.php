<?php

use App\Models\DataProcessingJob;
use Inertia\Testing\AssertableInertia as Assert;

test('shares a scoped pipeline status for users with access', function () {
    $user = makeUserWithPermissions(['data-processing.view']);

    DataProcessingJob::factory()->count(2)->create(['user_id' => $user->id]);
    DataProcessingJob::factory()->active()->create(['user_id' => $user->id]);
    DataProcessingJob::factory()->completed()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipelineStatus.active_count', 3)
            ->where('pipelineStatus.queued_count', 2)
            ->where('pipelineStatus.processing_count', 1)
            ->where('pipelineStatus.worst_status', 'info')
            ->has('pipelineStatus.runs', 3)
            ->has('pipelineStatus.runs.0', fn (Assert $run) => $run
                ->has('id')
                ->has('name')
                ->has('type')
                ->has('status')
                ->has('percentage')
                ->has('stage')
                ->has('eta_seconds')
                ->etc())
            ->where('pipelineStatus.freshness', null)
            ->where('pipelineStatus.api_budget', null)
            // Derived from the same aggregate for backward compatibility.
            ->where('activeJobs', 3));
});

test('surfaces recent failures with the error tone', function () {
    $user = makeUserWithPermissions(['data-processing.view']);

    DataProcessingJob::factory()->failed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipelineStatus.failed_recent', 1)
            ->where('pipelineStatus.worst_status', 'error'));
});

test('does not leak other users jobs to a scoped viewer', function () {
    $user = makeUserWithPermissions(['data-processing.view']);

    DataProcessingJob::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipelineStatus.active_count', 0)
            ->has('pipelineStatus.runs', 0));
});

test('shares an empty pipeline status without access', function () {
    $user = makeUserWithPermissions(['file.view']);

    DataProcessingJob::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipelineStatus.active_count', 0)
            ->where('pipelineStatus.worst_status', 'success')
            ->has('pipelineStatus.runs', 0));
});

test('shares an empty pipeline status for guests', function () {
    DataProcessingJob::factory()->count(2)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipelineStatus.active_count', 0)
            ->has('pipelineStatus.runs', 0));
});
