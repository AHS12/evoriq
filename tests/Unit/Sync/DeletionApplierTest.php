<?php

use App\DTOs\Sync\EntityChangeDTO;
use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\ClockifyDeletedEntity;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\DeletionApplier;
use App\Services\Sync\SyncHandlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

test('ingest records only deleted changes, idempotently', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $applier = app(DeletionApplier::class);

    $changes = [
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e1', EntityChangeType::DELETED, CarbonImmutable::parse('2026-06-01 10:00:00')),
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e2', EntityChangeType::UPDATED, CarbonImmutable::parse('2026-06-01 10:00:00')),
    ];

    expect($applier->ingest($workspace, $changes))->toBe(1)
        ->and($applier->ingest($workspace, $changes))->toBe(1)
        ->and(ClockifyDeletedEntity::query()->count())->toBe(1);
});

test('applyPending dispatches to the handler and marks applied', function () {
    $workspace = ClockifyWorkspace::factory()->create();

    $handler = Mockery::mock(SyncHandler::class);
    $handler->shouldReceive('delete')
        ->once()
        ->withArgs(fn ($context, string $id): bool => $id === 'e1');

    $registry = Mockery::mock(SyncHandlerRegistry::class);
    $registry->shouldReceive('has')->with(SyncEntityType::TIME_ENTRY)->andReturn(true);
    $registry->shouldReceive('resolve')->with(SyncEntityType::TIME_ENTRY)->andReturn($handler);

    app()->instance(SyncHandlerRegistry::class, $registry);

    $applier = app(DeletionApplier::class);

    $applier->ingest($workspace, [
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e1', EntityChangeType::DELETED, CarbonImmutable::now()),
    ]);

    expect($applier->applyPending($workspace))->toBe(1)
        ->and($applier->applyPending($workspace))->toBe(0)
        ->and(ClockifyDeletedEntity::query()->first()?->applied_at)->not->toBeNull();
});

test('deletions without a registered handler are left unapplied', function () {
    $workspace = ClockifyWorkspace::factory()->create();

    $registry = Mockery::mock(SyncHandlerRegistry::class);
    $registry->shouldReceive('has')->andReturn(false);
    app()->instance(SyncHandlerRegistry::class, $registry);

    $applier = app(DeletionApplier::class);

    $applier->ingest($workspace, [
        new EntityChangeDTO(SyncEntityType::INVOICES, 'i1', EntityChangeType::DELETED, CarbonImmutable::now()),
    ]);

    expect($applier->applyPending($workspace))->toBe(0)
        ->and(ClockifyDeletedEntity::query()->first()?->applied_at)->toBeNull();
});
