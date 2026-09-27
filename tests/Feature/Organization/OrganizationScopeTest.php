<?php

use App\Models\DataProcessingJob;
use App\Models\Organization;
use App\Support\OrganizationContext;

beforeEach(function () {
    Organization::forgetDefault();
    app(OrganizationContext::class)->reset();
});

it('seeds exactly one default organization', function () {
    $default = Organization::default();

    expect(Organization::query()->count())->toBe(1)
        ->and($default)->not->toBeNull()
        ->and($default->slug)->toBe('default')
        ->and($default->is_default)->toBeTrue();
});

it('stamps the default organization on create', function () {
    $job = DataProcessingJob::factory()->create();

    expect($job->organization_id)->toBe(Organization::default()->id);
});

it('scopes queries to the current organization', function () {
    $other = Organization::factory()->create();

    $ownJob = DataProcessingJob::factory()->create();
    DataProcessingJob::factory()->create(['organization_id' => $other->id]);

    expect(DataProcessingJob::query()->pluck('id')->all())->toBe([$ownJob->id]);
});

it('hides rows of another organization when the context switches', function () {
    $other = Organization::factory()->create();

    DataProcessingJob::factory()->create();
    $otherJob = DataProcessingJob::factory()->create(['organization_id' => $other->id]);

    app(OrganizationContext::class)->set($other);

    expect(DataProcessingJob::query()->pluck('id')->all())->toBe([$otherJob->id]);
});

it('can bypass the organization scope', function () {
    $other = Organization::factory()->create();

    DataProcessingJob::factory()->create();
    DataProcessingJob::factory()->create(['organization_id' => $other->id]);

    expect(DataProcessingJob::query()->count())->toBe(1)
        ->and(DataProcessingJob::withoutOrganizationScope()->count())->toBe(2)
        ->and(DataProcessingJob::forOrganization($other->id)->count())->toBe(1);
});
