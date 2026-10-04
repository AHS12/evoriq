<?php

use App\Enums\RawRecordSource;
use App\Enums\SyncEntityType;
use App\Models\ClockifyRawRecord;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\RawRecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('put persists a payload with a stable hash and source', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $store = app(RawRecordStore::class);

    $payload = ['id' => 'e1', 'description' => 'A'];

    $record = $store->put($workspace, SyncEntityType::TIME_ENTRY, 'e1', $payload, RawRecordSource::WEBHOOK);

    expect($record)->not->toBeNull()
        ->and($record->payload)->toBe($payload)
        ->and($record->payload_hash)->toBe(hash('sha256', (string) json_encode($payload)))
        ->and($record->source)->toBe(RawRecordSource::WEBHOOK->value)
        ->and($record->workspace_id)->toBe($workspace->id);
});

test('put skips the write when the payload is unchanged', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $store = app(RawRecordStore::class);

    $payload = ['id' => 'e1', 'description' => 'A'];

    $store->put($workspace, SyncEntityType::TIME_ENTRY, 'e1', $payload);

    expect($store->put($workspace, SyncEntityType::TIME_ENTRY, 'e1', $payload))->toBeNull()
        ->and(ClockifyRawRecord::query()->count())->toBe(1);
});

test('put refreshes the payload when it changes', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $store = app(RawRecordStore::class);

    $store->put($workspace, SyncEntityType::TIME_ENTRY, 'e1', ['id' => 'e1', 'description' => 'A']);
    $store->put($workspace, SyncEntityType::TIME_ENTRY, 'e1', ['id' => 'e1', 'description' => 'B']);

    $record = ClockifyRawRecord::query()->where('clockify_id', 'e1')->first();

    expect(ClockifyRawRecord::query()->count())->toBe(1)
        ->and($record?->payload['description'])->toBe('B');
});

test('putMany persists a page once and dedupes on re-run', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $store = app(RawRecordStore::class);

    $items = [
        ['id' => 'e1', 'description' => 'A'],
        ['id' => 'e2', 'description' => 'B'],
        ['not_an_entity' => true],
    ];

    expect($store->putMany($workspace, SyncEntityType::TIME_ENTRY, $items))->toBe(2)
        ->and($store->putMany($workspace, SyncEntityType::TIME_ENTRY, $items))->toBe(0)
        ->and($store->putMany($workspace, SyncEntityType::TIME_ENTRY, [['id' => 'e1', 'description' => 'A2']]))->toBe(1);

    expect(ClockifyRawRecord::query()->count())->toBe(2);
});

test('get returns the stored record and deleteFor removes it', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $store = app(RawRecordStore::class);

    $store->putMany($workspace, SyncEntityType::TIME_ENTRY, [
        ['id' => 'e1'],
        ['id' => 'e2'],
    ]);

    expect($store->get($workspace, SyncEntityType::TIME_ENTRY, 'e1'))->not->toBeNull()
        ->and($store->deleteFor($workspace, SyncEntityType::TIME_ENTRY))->toBe(2)
        ->and(ClockifyRawRecord::query()->count())->toBe(0);
});
