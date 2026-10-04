<?php

use App\Enums\ApiUsageWindowType;
use App\Models\ClockifyConnection;
use App\Services\Sync\ApiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('free connections use an hourly window and paid a per-second window', function () {
    $free = ClockifyConnection::factory()->free()->create();
    $paid = ClockifyConnection::factory()->create([
        'requests_per_hour' => null,
        'requests_per_second' => 50,
    ]);

    $service = app(ApiUsageService::class);

    expect($service->snapshot($free)->windowType)->toBe(ApiUsageWindowType::HOUR)
        ->and($service->snapshot($paid)->windowType)->toBe(ApiUsageWindowType::SECOND);
});

test('reserve spends the window only up to the safety margin', function () {
    config(['clockify.budget_safety_factor' => 0.9]);

    $connection = ClockifyConnection::factory()->free()->create(); // 30 per hour
    $service = app(ApiUsageService::class);

    for ($i = 0; $i < 27; $i++) {
        expect($service->reserve($connection))->toBeTrue();
    }

    expect($service->reserve($connection))->toBeFalse();

    $snapshot = $service->snapshot($connection);

    expect($snapshot->used)->toBe(27)
        ->and($snapshot->remaining)->toBe(3)
        ->and($snapshot->resetsIn)->toBeGreaterThan(0)
        ->and($snapshot->isExhausted())->toBeFalse()
        ->and($snapshot->isLow())->toBeTrue();
});

test('canAfford reflects the effective budget without spending it', function () {
    $connection = ClockifyConnection::factory()->free()->create();
    $service = app(ApiUsageService::class);

    expect($service->canAfford($connection, null, 27))->toBeTrue()
        ->and($service->canAfford($connection, null, 28))->toBeFalse()
        ->and($service->snapshot($connection)->used)->toBe(0);
});

test('recording a rate limited response marks the window exhausted', function () {
    $connection = ClockifyConnection::factory()->free()->create();
    $service = app(ApiUsageService::class);

    $service->record($connection, null, 429);

    $snapshot = $service->snapshot($connection);

    expect($snapshot->remaining)->toBe(0)
        ->and($snapshot->isExhausted())->toBeTrue()
        ->and($snapshot->lastRequestAt)->not->toBeNull();
});

test('a lower safety factor leaves more headroom', function () {
    config(['clockify.budget_safety_factor' => 0.5]);

    $connection = ClockifyConnection::factory()->free()->create();
    $service = app(ApiUsageService::class);

    expect($service->canAfford($connection, null, 15))->toBeTrue()
        ->and($service->canAfford($connection, null, 16))->toBeFalse();
});
