<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyRawRecord;
use App\Models\ClockifyWorkspace;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\RawRecordStore;
use Illuminate\Support\Facades\Http;

test('a fetched page is persisted with matching payloads', function () {
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()
        ->for($connection, 'connection')
        ->create(['clockify_id' => 'ws-1']);

    Http::fake([
        '*/workspaces/ws-1/user/u1/time-entries*' => Http::response([
            ['id' => 'e1', 'description' => 'A'],
            ['id' => 'e2', 'description' => 'B'],
        ], 200, ['Last-Page' => 'true']),
    ]);

    $store = app(RawRecordStore::class);
    $client = app(ClockifyClient::class)->forConnection($connection, $workspace);

    foreach ($client->paginate('/workspaces/ws-1/user/u1/time-entries') as $page) {
        $store->putMany($workspace, SyncEntityType::TIME_ENTRY, $page);
    }

    $first = ClockifyRawRecord::query()->where('clockify_id', 'e1')->first();
    $second = ClockifyRawRecord::query()->where('clockify_id', 'e2')->first();

    expect(ClockifyRawRecord::query()->count())->toBe(2)
        ->and($first?->payload['description'])->toBe('A')
        ->and($second?->payload['description'])->toBe('B')
        ->and($first?->workspace_id)->toBe($workspace->id);
});
