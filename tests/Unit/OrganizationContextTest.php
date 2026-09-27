<?php

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Organization::forgetDefault();
    app(OrganizationContext::class)->reset();
});

it('resolves the default organization when nothing else is selected', function () {
    $context = app(OrganizationContext::class);

    expect($context->id())->toBe(Organization::default()->id)
        ->and($context->model()?->is_default)->toBeTrue();
});

it('prefers an explicitly selected organization', function () {
    $organization = Organization::factory()->create();

    $context = app(OrganizationContext::class);
    $context->set($organization);

    expect($context->id())->toBe($organization->id)
        ->and($context->model()?->is($organization))->toBeTrue();
});

it('prefers the authenticated user organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    expect(app(OrganizationContext::class)->id())->toBe($organization->id);
});

it('memoizes the resolved organization', function () {
    $organization = Organization::factory()->create();

    $context = app(OrganizationContext::class);
    $context->set($organization);

    $other = Organization::factory()->create();
    $context->set($other);

    expect($context->id())->toBe($other->id);
});

it('re-resolves after a reset', function () {
    $context = app(OrganizationContext::class);
    $context->set(Organization::factory()->create());

    $context->reset();

    expect($context->id())->toBe(Organization::default()->id);
});
