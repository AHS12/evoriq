<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\WorkspaceSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

/**
 * Fetch → map → idempotent upsert exactly as the runner would (ENT-00). The
 * caller stubs the HTTP response once per test.
 */
function fetchAndUpsertWorkspace(ClockifyWorkspace $workspace): void
{
    $handler = app(WorkspaceSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

/**
 * @return array{0: ClockifyWorkspace, 1: array<string, mixed>}
 */
function makeWorkspaceFixture(): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create([
        'clockify_id' => '68866b1c06b68d6e9a719271',
    ]);

    $payload = [
        'id' => '68866b1c06b68d6e9a719271',
        'name' => 'Innovix Matrix System',
        'subdomain' => ['name' => 'innovix', 'enabled' => true],
        'hourlyRate' => ['amount' => 75.5, 'currency' => 'USD'],
        'costRate' => ['amount' => 40, 'currency' => 'USD'],
        'defaultBillable' => true,
        'featureSubscriptionType' => 'FREE_2026',
        'features' => ['TIME_TRACKING', 'ONE_MONTH_RANGE_REPORTS'],
        'workspaceSettings' => ['weekStart' => 'SATURDAY', 'timeZone' => 'Asia/Dhaka'],
        'currencies' => [['code' => 'USD', 'isDefault' => true]],
        'cakeOrganizationId' => '68335ed8f89dcf67d78cef2e',
    ];

    return [$workspace, $payload];
}

test('the workspace handler is registered for the workspace entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::WORKSPACE))->toBeTrue();
});

test('it persists every workspace dimension field', function () {
    [$workspace, $payload] = makeWorkspaceFixture();
    $connectionId = $workspace->connection_id;

    Http::fake(['*' => Http::response($payload, 200)]);

    fetchAndUpsertWorkspace($workspace);

    $workspace->refresh();

    expect($workspace->name)->toBe('Innovix Matrix System')
        ->and($workspace->subdomain)->toBe('innovix')
        ->and($workspace->currency)->toBe('USD')
        ->and($workspace->time_zone)->toBe('Asia/Dhaka')
        ->and($workspace->week_start)->toBe('SATURDAY')
        ->and($workspace->default_billable)->toBeTrue()
        ->and((float) $workspace->default_hourly_rate)->toBe(75.5)
        ->and((float) $workspace->default_cost_rate)->toBe(40.0)
        ->and($workspace->feature_subscription_type)->toBe('FREE_2026')
        ->and($workspace->cake_organization_id)->toBe('68335ed8f89dcf67d78cef2e')
        ->and($workspace->features)->toBe(['TIME_TRACKING', 'ONE_MONTH_RANGE_REPORTS'])
        ->and($workspace->workspace_settings)->toBe(['weekStart' => 'SATURDAY', 'timeZone' => 'Asia/Dhaka'])
        ->and($workspace->connection_id)->toBe($connectionId)
        ->and($workspace->raw_data)->toBeArray();
});

test('a partial payload does not break the sync or erase existing values', function () {
    [$workspace] = makeWorkspaceFixture();

    Http::fake(['*' => Http::response([
        'id' => '68866b1c06b68d6e9a719271',
        'name' => 'Renamed Workspace',
    ], 200)]);

    fetchAndUpsertWorkspace($workspace);

    $workspace->refresh();

    expect($workspace->name)->toBe('Renamed Workspace')
        ->and($workspace->currency)->toBe('USD')
        ->and($workspace->time_zone)->toBe('UTC');
});

test('re-syncing the same workspace does not duplicate it', function () {
    [$workspace, $payload] = makeWorkspaceFixture();

    Http::fake(['*' => Http::response($payload, 200)]);

    fetchAndUpsertWorkspace($workspace);
    fetchAndUpsertWorkspace($workspace);

    expect(ClockifyWorkspace::query()
        ->where('clockify_id', '68866b1c06b68d6e9a719271')
        ->count())->toBe(1);
});

test('re-syncing updates changed fields in place', function () {
    [$workspace, $payload] = makeWorkspaceFixture();

    Http::fakeSequence('*')
        ->push($payload, 200)
        ->push([...$payload, 'name' => 'Innovix v2', 'currency' => 'EUR'], 200);

    fetchAndUpsertWorkspace($workspace);
    fetchAndUpsertWorkspace($workspace);

    $workspace->refresh();

    expect($workspace->name)->toBe('Innovix v2')
        ->and($workspace->currency)->toBe('EUR')
        ->and(ClockifyWorkspace::query()
            ->where('clockify_id', '68866b1c06b68d6e9a719271')
            ->count())->toBe(1);
});

test('deleting a workspace is a no-op', function () {
    [$workspace, $payload] = makeWorkspaceFixture();

    Http::fake(['*' => Http::response($payload, 200)]);

    fetchAndUpsertWorkspace($workspace);

    app(WorkspaceSyncHandler::class)->delete(
        new SyncContext($workspace->connection, $workspace),
        '68866b1c06b68d6e9a719271',
    );

    expect(ClockifyWorkspace::query()
        ->where('clockify_id', '68866b1c06b68d6e9a719271')
        ->count())->toBe(1);
});
