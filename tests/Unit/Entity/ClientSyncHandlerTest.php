<?php

use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Models\ClockifyClient;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\ClientSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use App\Services\Sync\SyncJobRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

/**
 * Fetch → map → idempotent upsert exactly as the runner would (ENT-00).
 */
function fetchAndUpsertClients(ClockifyWorkspace $workspace): void
{
    $handler = app(ClientSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

function makeClientsWorkspace(array $attributes = []): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create($attributes);
}

function makeClientJob(ClockifyWorkspace $workspace, int $pageSize = 200): ClockifySyncJob
{
    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $workspace->connection_id,
        'workspace_id' => $workspace->id,
    ]);

    return ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::CLIENTS,
        'phase' => SyncPhase::REFERENCE,
        'page_size' => $pageSize,
    ]);
}

test('the client handler is registered for the clients entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::CLIENTS))->toBeTrue();
});

test('it persists client fields, archived flags and currency fallback', function () {
    $workspace = makeClientsWorkspace(['currency' => 'EUR']);

    Http::fake(['*' => Http::response([
        [
            'id' => 'client-1',
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
            'address' => '1 Road',
            'note' => 'VIP',
            'currencyCode' => 'GBP',
            'archived' => false,
        ],
        [
            'id' => 'client-2',
            'name' => 'Old Co',
            'archived' => true,
            'archivedAt' => '2026-01-15T10:00:00Z',
        ],
    ], 200)]);

    fetchAndUpsertClients($workspace);

    $client = ClockifyClient::query()->where('clockify_id', 'client-1')->firstOrFail();
    $archived = ClockifyClient::query()->where('clockify_id', 'client-2')->firstOrFail();

    expect($client->name)->toBe('Acme Corp')
        ->and($client->email)->toBe('billing@acme.test')
        ->and($client->address)->toBe('1 Road')
        ->and($client->note)->toBe('VIP')
        ->and($client->currency_code)->toBe('GBP')
        ->and($client->archived)->toBeFalse()
        ->and($client->raw_data)->toBeArray()
        ->and($archived->archived)->toBeTrue()
        ->and($archived->archived_at->toDateString())->toBe('2026-01-15')
        ->and($archived->currency_code)->toBe('EUR');
});

test('the runner pages through every client', function () {
    $workspace = makeClientsWorkspace();
    $job = makeClientJob($workspace, pageSize: 2);

    Http::fake(fn ($request) => (int) ($request['page'] ?? 1) === 1
        ? Http::response([['id' => 'c1', 'name' => 'A'], ['id' => 'c2', 'name' => 'B']], 200)
        : Http::response([['id' => 'c3', 'name' => 'C']], 200));

    app(SyncJobRunner::class)->run($job);

    $job->refresh();

    expect(ClockifyClient::query()->count())->toBe(3)
        ->and($job->records_created)->toBe(3)
        ->and($job->page)->toBe(2);
});

test('re-syncing is idempotent and reflects unarchiving', function () {
    $workspace = makeClientsWorkspace();

    Http::fakeSequence('*')
        ->push([['id' => 'client-1', 'name' => 'Acme', 'archived' => true, 'archivedAt' => '2026-01-01T00:00:00Z']], 200)
        ->push([['id' => 'client-1', 'name' => 'Acme', 'archived' => false]], 200);

    fetchAndUpsertClients($workspace);
    fetchAndUpsertClients($workspace);

    $client = ClockifyClient::query()->firstOrFail();

    expect(ClockifyClient::query()->count())->toBe(1)
        ->and($client->archived)->toBeFalse()
        ->and($client->archived_at)->toBeNull();
});

test('deleting a client soft-deletes it and a re-sync restores it', function () {
    $workspace = makeClientsWorkspace();

    Http::fake(['*' => Http::response([['id' => 'client-1', 'name' => 'Acme']], 200)]);

    fetchAndUpsertClients($workspace);

    $context = new SyncContext($workspace->connection, $workspace);

    app(ClientSyncHandler::class)->delete($context, 'client-1');

    expect(ClockifyClient::query()->count())->toBe(0)
        ->and(ClockifyClient::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();

    fetchAndUpsertClients($workspace);

    expect(ClockifyClient::query()->count())->toBe(1)
        ->and(ClockifyClient::query()->firstOrFail()->deleted_at)->toBeNull();
});
