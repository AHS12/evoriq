<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyProject;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryRate;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\TimeEntryRateSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function makeRatesWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create(['currency' => 'USD']);
}

function makeRateEntry(ClockifyWorkspace $workspace, string $clockifyId, ?int $projectId = null): ClockifyTimeEntry
{
    $user = ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'user-1',
    ]);

    return ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'project_id' => $projectId,
        'clockify_id' => $clockifyId,
    ]);
}

function runRateSync(ClockifyWorkspace $workspace, string $userId): void
{
    $handler = app(TimeEntryRateSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace, pageSize: 200, userId: $userId);

    $rows = [];

    for ($page = 1; ; $page++) {
        $items = $handler->fetchPage($context, $page);
        $items = is_array($items) ? $items : iterator_to_array($items, false);

        if ($items === []) {
            break;
        }

        foreach ($items as $raw) {
            try {
                $rows[] = $handler->map($raw, $context);
            } catch (Throwable) {
                // skip malformed
            }
        }

        if (count($items) < 200) {
            break;
        }
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rateEntryPayload(string $id, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'userId' => 'user-1',
        'timeInterval' => ['start' => '2026-05-01T09:00:00Z', 'end' => '2026-05-01T10:00:00Z'],
    ], $overrides);
}

test('the rate handler is registered for the time entry rate entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::TIME_ENTRY_RATE))->toBeTrue();
});

test('a hydrated entry yields a rate row with snapshots', function () {
    $workspace = makeRatesWorkspace();
    $project = ClockifyProject::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'project-1',
    ]);
    $entry = makeRateEntry($workspace, 'e1', $project->id);

    Http::fake(['*' => Http::response([
        rateEntryPayload('e1', [
            'hourlyRate' => ['amount' => 120, 'currency' => 'EUR'],
            'costRate' => ['amount' => 70],
        ]),
    ], 200)]);

    runRateSync($workspace, 'user-1');

    $rate = ClockifyTimeEntryRate::query()->firstOrFail();

    expect($rate->time_entry_id)->toBe($entry->id)
        ->and($rate->user_id)->toBe($entry->user_id)
        ->and($rate->project_id)->toBe($project->id)
        ->and((float) $rate->billable_rate_amount)->toBe(120.0)
        ->and($rate->billable_rate_currency)->toBe('EUR')
        ->and((float) $rate->cost_rate_amount)->toBe(70.0)
        ->and($rate->cost_rate_currency)->toBe('USD');
});

test('an entry without explicit rates still yields a null rate row', function () {
    $workspace = makeRatesWorkspace();
    makeRateEntry($workspace, 'e1');

    Http::fake(['*' => Http::response([rateEntryPayload('e1')], 200)]);

    runRateSync($workspace, 'user-1');

    $rate = ClockifyTimeEntryRate::query()->firstOrFail();

    expect($rate->billable_rate_amount)->toBeNull()
        ->and($rate->cost_rate_amount)->toBeNull();
});

test('a rate revision updates the same row', function () {
    $workspace = makeRatesWorkspace();
    makeRateEntry($workspace, 'e1');

    Http::fakeSequence('*')
        ->push([rateEntryPayload('e1', ['hourlyRate' => ['amount' => 100]])], 200)
        ->push([rateEntryPayload('e1', ['hourlyRate' => ['amount' => 130]])], 200);

    runRateSync($workspace, 'user-1');
    runRateSync($workspace, 'user-1');

    expect(ClockifyTimeEntryRate::query()->count())->toBe(1)
        ->and((float) ClockifyTimeEntryRate::query()->firstOrFail()->billable_rate_amount)->toBe(130.0);
});

test('re-syncing rates is idempotent', function () {
    $workspace = makeRatesWorkspace();
    makeRateEntry($workspace, 'e1');

    Http::fake(['*' => Http::response([rateEntryPayload('e1', ['hourlyRate' => ['amount' => 100]])], 200)]);

    runRateSync($workspace, 'user-1');
    runRateSync($workspace, 'user-1');

    expect(ClockifyTimeEntryRate::query()->count())->toBe(1);
});
