<?php

use App\DTOs\Sync\EntityChangeDTO;
use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\ClockifyEntityChange;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\EntityChangeRecorder;
use Carbon\CarbonImmutable;

test('recording the same change twice is idempotent', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $sourceAt = CarbonImmutable::parse('2026-06-01 10:00:00');

    $changes = [
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e1', EntityChangeType::UPDATED, $sourceAt, ['id' => 'e1']),
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e2', EntityChangeType::CREATED, $sourceAt, ['id' => 'e2']),
    ];

    $recorder = app(EntityChangeRecorder::class);

    expect($recorder->record($workspace, $changes))->toBe(2)
        ->and($recorder->record($workspace, $changes))->toBe(2)
        ->and(ClockifyEntityChange::query()->count())->toBe(2);
});

test('a later change event at a different source time adds a new row', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $recorder = app(EntityChangeRecorder::class);

    $recorder->record($workspace, [
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e1', EntityChangeType::UPDATED, CarbonImmutable::parse('2026-06-01 10:00:00')),
    ]);
    $recorder->record($workspace, [
        new EntityChangeDTO(SyncEntityType::TIME_ENTRY, 'e1', EntityChangeType::UPDATED, CarbonImmutable::parse('2026-06-02 10:00:00')),
    ]);

    expect(ClockifyEntityChange::query()->count())->toBe(2);
});
