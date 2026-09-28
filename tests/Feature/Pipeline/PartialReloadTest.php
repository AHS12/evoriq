<?php

use App\Enums\DataProcessingJobStatus;
use App\Models\DataProcessingJob;
use Inertia\Testing\AssertableInertia as Assert;

test('partial reload returns only the polling props', function () {
    $user = makeUserWithPermissions(['data-processing.view']);

    DataProcessingJob::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('data-processing/index')
            ->reloadOnly(
                'jobs,stats,activeJobs,pipeline_revision',
                fn (Assert $reload) => $reload
                    ->has('jobs')
                    ->has('stats')
                    ->has('activeJobs')
                    ->has('pipeline_revision')
                    ->missing('options')
                    ->missing('filters')
                    ->missing('notifications')
                    ->missing('auth'),
            ));
});

test('the pipeline revision is stable until a job changes', function () {
    $user = makeUserWithPermissions(['data-processing.view']);

    $job = DataProcessingJob::factory()->create(['user_id' => $user->id]);

    $first = $this->actingAs($user)
        ->get(route('activity.index'))
        ->inertiaProps('pipeline_revision');

    $second = $this->actingAs($user)
        ->get(route('activity.index'))
        ->inertiaProps('pipeline_revision');

    expect($first)->toBeString()
        ->and($second)->toBe($first);

    $this->travel(1)->seconds();

    $job->update(['status' => DataProcessingJobStatus::PROCESSING]);

    $third = $this->actingAs($user)
        ->get(route('activity.index'))
        ->inertiaProps('pipeline_revision');

    expect($third)->not->toBe($first);
});

test('users without access receive an empty pipeline revision', function () {
    $user = makeUserWithPermissions(['file.view']);

    DataProcessingJob::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pipeline_revision', ''));
});
