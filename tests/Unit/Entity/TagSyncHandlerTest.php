<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyTag;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryTag;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\TimeEntryTagRepositoryInterface;
use App\Services\Sync\Handlers\TagSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function makeTagsWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

function makeTag(ClockifyWorkspace $workspace, string $clockifyId, bool $archived = false): ClockifyTag
{
    return ClockifyTag::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
        'archived' => $archived,
    ]);
}

function fetchAndUpsertTags(ClockifyWorkspace $workspace): void
{
    $handler = app(TagSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

test('the tag handler is registered for the tags entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::TAGS))->toBeTrue();
});

test('it persists tag fields including the archived flag', function () {
    $workspace = makeTagsWorkspace();

    Http::fake(['*' => Http::response([
        ['id' => 'tag-1', 'name' => 'Billable', 'archived' => false],
        ['id' => 'tag-2', 'name' => 'Legacy', 'archived' => true, 'archivedAt' => '2026-02-01T00:00:00Z'],
    ], 200)]);

    fetchAndUpsertTags($workspace);

    expect(ClockifyTag::query()->count())->toBe(2)
        ->and(ClockifyTag::query()->where('clockify_id', 'tag-1')->firstOrFail()->name)->toBe('Billable')
        ->and(ClockifyTag::query()->where('clockify_id', 'tag-2')->firstOrFail()->archived)->toBeTrue()
        ->and(ClockifyTag::query()->where('clockify_id', 'tag-2')->firstOrFail()->archived_at->toDateString())->toBe('2026-02-01');
});

test('re-syncing tags is idempotent', function () {
    $workspace = makeTagsWorkspace();

    Http::fake(['*' => Http::response([['id' => 'tag-1', 'name' => 'Billable']], 200)]);

    fetchAndUpsertTags($workspace);
    fetchAndUpsertTags($workspace);

    expect(ClockifyTag::query()->count())->toBe(1);
});

test('time-entry tag joins reflect adds and removals', function () {
    $workspace = makeTagsWorkspace();
    $user = ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
    ]);
    $entry = ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    makeTag($workspace, 'tag-1');
    makeTag($workspace, 'tag-2');

    $repository = app(TimeEntryTagRepositoryInterface::class);

    $repository->syncForTimeEntry($workspace, $entry, ['tag-1', 'tag-2', 'unknown-tag']);
    expect(ClockifyTimeEntryTag::query()->where('time_entry_id', $entry->id)->count())->toBe(2);

    $repository->syncForTimeEntry($workspace, $entry, ['tag-1']);
    expect(ClockifyTimeEntryTag::query()->where('time_entry_id', $entry->id)->count())->toBe(1);

    $repository->syncForTimeEntry($workspace, $entry, []);
    expect(ClockifyTimeEntryTag::query()->where('time_entry_id', $entry->id)->count())->toBe(0);
});
