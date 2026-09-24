<?php

use App\Models\DataProcessingJob;
use Inertia\Testing\AssertableInertia as Assert;

test('shares the active job count for users with access', function () {
    $user = makeUserWithPermissions(['data-processing.view', 'user.export']);

    DataProcessingJob::factory()->count(2)->create(['user_id' => $user->id]);
    DataProcessingJob::factory()->completed()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('activeJobs', 2));
});

test('shares zero active jobs without access', function () {
    $user = makeUserWithPermissions(['file.view']);

    DataProcessingJob::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('activeJobs', 0));
});
