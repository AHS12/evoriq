<?php

use App\Checks\PipelineHealthCheck;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Health\Facades\Health;

test('a developer can view the pipeline health page', function () {
    $this->actingAs(superAdmin())
        ->get(route('admin.settings.developer.pipeline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('developer/pipeline')
            ->has('metrics')
            ->has('metrics.active_now')
            ->has('queue.channels')
            ->has('health')
            ->has('status')
            ->has('recentRuns')
            ->has('recentFailures')
            ->has('apiBudget')
            ->has('tools'));
});

test('a non-developer cannot view the pipeline health page', function () {
    $this->actingAs(member())
        ->get(route('admin.settings.developer.pipeline'))
        ->assertForbidden();
});

test('the pipeline health check is registered', function () {
    $registered = Health::registeredChecks()
        ->contains(fn ($check): bool => $check instanceof PipelineHealthCheck);

    expect($registered)->toBeTrue();
});
