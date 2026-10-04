<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyProject;
use App\Models\ClockifyTag;
use App\Models\ClockifyTask;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryTag;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\TimeEntrySyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function makeEntriesWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create(['currency' => 'USD']);
}

function makeEntryUser(ClockifyWorkspace $workspace, string $clockifyId): ClockifyUser
{
    return ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

/**
 * Page through the per-user endpoint as the runner would, tolerating per-row
 * mapping failures (ENT-00), then upsert.
 */
function runTimeEntrySync(ClockifyWorkspace $workspace, string $userId, int $pageSize = 200): void
{
    $handler = app(TimeEntrySyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace, pageSize: $pageSize, userId: $userId);

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
                // malformed / untracked row
            }
        }

        if (count($items) < $pageSize) {
            break;
        }
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function entryPayload(string $id, string $userId, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'userId' => $userId,
        'description' => 'Work',
        'timeInterval' => ['start' => '2026-05-01T09:00:00Z', 'end' => '2026-05-01T10:30:00Z'],
        'billable' => true,
        'type' => 'REGULAR',
        'tagIds' => [],
    ], $overrides);
}

test('the time entry handler is registered for the time entry entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::TIME_ENTRY))->toBeTrue();
});

test('it pages per user and resolves the user', function () {
    $workspace = makeEntriesWorkspace();
    $user = makeEntryUser($workspace, 'user-1');

    Http::fake(fn ($request) => (int) ($request['page'] ?? 1) === 1
        ? Http::response([entryPayload('e1', 'user-1'), entryPayload('e2', 'user-1')], 200)
        : Http::response([entryPayload('e3', 'user-1')], 200));

    runTimeEntrySync($workspace, 'user-1', pageSize: 2);

    expect(ClockifyTimeEntry::query()->count())->toBe(3)
        ->and(ClockifyTimeEntry::query()->where('clockify_id', 'e1')->firstOrFail()->user_id)->toBe($user->id);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/user/user-1/time-entries')
        && $request['hydrated'] === 'true');
});

test('it derives duration and handles running and untracked entries', function () {
    $workspace = makeEntriesWorkspace();
    makeEntryUser($workspace, 'user-1');

    Http::fake(['*' => Http::response([
        entryPayload('done', 'user-1'),
        entryPayload('running', 'user-1', ['timeInterval' => ['start' => '2026-05-01T11:00:00Z', 'end' => null]]),
        entryPayload('untracked', 'user-1', ['timeInterval' => null]),
    ], 200)]);

    runTimeEntrySync($workspace, 'user-1');

    $done = ClockifyTimeEntry::query()->where('clockify_id', 'done')->firstOrFail();
    $running = ClockifyTimeEntry::query()->where('clockify_id', 'running')->firstOrFail();

    expect($done->duration_seconds)->toBe(5400)
        ->and($running->duration_seconds)->toBeNull()
        ->and($running->is_in_progress)->toBeTrue()
        ->and(ClockifyTimeEntry::query()->where('clockify_id', 'untracked')->exists())->toBeFalse();
});

test('it resolves the project and task', function () {
    $workspace = makeEntriesWorkspace();
    makeEntryUser($workspace, 'user-1');
    $project = ClockifyProject::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'project-1',
    ]);
    $task = ClockifyTask::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'project_id' => $project->id,
        'clockify_id' => 'task-1',
    ]);

    Http::fake(['*' => Http::response([
        entryPayload('e1', 'user-1', ['projectId' => 'project-1', 'taskId' => 'task-1']),
    ], 200)]);

    runTimeEntrySync($workspace, 'user-1');

    $entry = ClockifyTimeEntry::query()->firstOrFail();

    expect($entry->project_id)->toBe($project->id)
        ->and($entry->task_id)->toBe($task->id);
});

test('it writes tag joins and reflects removals', function () {
    $workspace = makeEntriesWorkspace();
    makeEntryUser($workspace, 'user-1');

    foreach (['tag-1', 'tag-2'] as $tagId) {
        ClockifyTag::factory()->create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'clockify_id' => $tagId,
        ]);
    }

    Http::fakeSequence('*')
        ->push([entryPayload('e1', 'user-1', ['tagIds' => ['tag-1', 'tag-2']])], 200)
        ->push([entryPayload('e1', 'user-1', ['tagIds' => ['tag-1']])], 200);

    runTimeEntrySync($workspace, 'user-1');
    expect(ClockifyTimeEntryTag::query()->count())->toBe(2);

    runTimeEntrySync($workspace, 'user-1');

    expect(ClockifyTimeEntry::query()->count())->toBe(1)
        ->and(ClockifyTimeEntryTag::query()->count())->toBe(1);
});

test('re-syncing time entries is idempotent', function () {
    $workspace = makeEntriesWorkspace();
    makeEntryUser($workspace, 'user-1');

    Http::fake(['*' => Http::response([entryPayload('e1', 'user-1')], 200)]);

    runTimeEntrySync($workspace, 'user-1');
    runTimeEntrySync($workspace, 'user-1');

    expect(ClockifyTimeEntry::query()->count())->toBe(1);
});
