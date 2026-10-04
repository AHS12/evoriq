<?php

use App\Models\ClockifyClient;
use App\Models\ClockifyConnection;
use App\Models\ClockifyMembership;
use App\Models\ClockifyProject;
use App\Models\ClockifyTag;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryTag;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserGroup;
use App\Models\ClockifyUserGroupMember;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\ClientSyncHandler;
use App\Services\Sync\Handlers\ProjectSyncHandler;
use App\Services\Sync\Handlers\TagSyncHandler;
use App\Services\Sync\Handlers\UserGroupSyncHandler;
use App\Services\Sync\Handlers\UserSyncHandler;
use App\Services\Sync\Handlers\WorkspaceSyncHandler;
use App\Services\Sync\SyncContext;
use Illuminate\Support\Facades\Http;

function policyScope(): array
{
    $connection = ClockifyConnection::factory()->create();
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create();

    return [new SyncContext($connection, $workspace), $workspace];
}

function policyUser(ClockifyWorkspace $workspace, string $clockifyId): ClockifyUser
{
    return ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

test('deleting a tag soft-deletes it and removes its entry joins', function () {
    [$context, $workspace] = policyScope();
    $tag = ClockifyTag::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'tag-1',
    ]);
    $entry = ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => policyUser($workspace, 'user-1')->id,
    ]);
    ClockifyTimeEntryTag::query()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'time_entry_id' => $entry->id,
        'tag_id' => $tag->id,
    ]);

    app(TagSyncHandler::class)->delete($context, 'tag-1');

    expect(ClockifyTag::query()->count())->toBe(0)
        ->and(ClockifyTag::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull()
        ->and(ClockifyTimeEntryTag::query()->count())->toBe(0);
});

test('deleting a user group soft-deletes it and removes its members', function () {
    [$context, $workspace] = policyScope();
    $group = ClockifyUserGroup::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'group-1',
    ]);
    $user = policyUser($workspace, 'user-1');
    ClockifyUserGroupMember::query()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_group_id' => $group->id,
        'user_id' => $user->id,
    ]);

    app(UserGroupSyncHandler::class)->delete($context, 'group-1');

    expect(ClockifyUserGroup::query()->count())->toBe(0)
        ->and(ClockifyUserGroup::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull()
        ->and(ClockifyUserGroupMember::query()->count())->toBe(0);
});

test('deleting a user soft-deletes it and removes memberships but keeps entries', function () {
    [$context, $workspace] = policyScope();
    $user = policyUser($workspace, 'user-1');
    $entry = ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);
    ClockifyMembership::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    app(UserSyncHandler::class)->delete($context, 'user-1');

    expect(ClockifyUser::query()->count())->toBe(0)
        ->and(ClockifyUser::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull()
        ->and(ClockifyMembership::query()->count())->toBe(0);

    // The historical time entry is untouched and still references the user.
    expect(ClockifyTimeEntry::query()->find($entry->id))->not->toBeNull();
});

test('deleting a workspace marks it inactive without destroying rows', function () {
    [$context, $workspace] = policyScope();

    app(WorkspaceSyncHandler::class)->delete($context, $workspace->clockify_id);

    $fresh = ClockifyWorkspace::query()->findOrFail($workspace->id);

    expect($fresh->active)->toBeFalse();
});

test('deleting a project does not destroy its time entries', function () {
    [$context, $workspace] = policyScope();
    $project = ClockifyProject::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'project-1',
    ]);
    $entry = ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => policyUser($workspace, 'user-1')->id,
        'project_id' => $project->id,
    ]);

    app(ProjectSyncHandler::class)->delete($context, 'project-1');

    expect(ClockifyProject::query()->count())->toBe(0)
        ->and(ClockifyTimeEntry::query()->findOrFail($entry->id)->project_id)->toBe($project->id);
});

test('re-applying a deletion is a no-op', function () {
    [$context, $workspace] = policyScope();
    ClockifyClient::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'client-1',
    ]);

    $handler = app(ClientSyncHandler::class);
    $handler->delete($context, 'client-1');
    $first = ClockifyClient::withTrashed()->firstOrFail()->deleted_at;

    $handler->delete($context, 'client-1');

    expect(ClockifyClient::withTrashed()->firstOrFail()->deleted_at->equalTo($first))->toBeTrue();
});

test('a delete then re-sync restores the entity', function () {
    [$context, $workspace] = policyScope();
    ClockifyClient::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'client-1',
    ]);

    app(ClientSyncHandler::class)->delete($context, 'client-1');
    expect(ClockifyClient::query()->count())->toBe(0);

    Http::fake(['*' => Http::response([['id' => 'client-1', 'name' => 'Acme']], 200)]);

    $handler = app(ClientSyncHandler::class);
    $rows = [];
    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }
    $handler->repository()->upsertMany($workspace, $rows);

    expect(ClockifyClient::query()->count())->toBe(1)
        ->and(ClockifyClient::query()->firstOrFail()->deleted_at)->toBeNull();
});

test('soft-deleted facts are excluded from queries by default but retained historically', function () {
    [, $workspace] = policyScope();
    $entry = ClockifyTimeEntry::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'user_id' => policyUser($workspace, 'user-1')->id,
    ]);

    $entry->delete();

    expect(ClockifyTimeEntry::query()->count())->toBe(0)
        ->and(ClockifyTimeEntry::withTrashed()->count())->toBe(1);

    // A soft-deleted client still resolves for historical project names.
    $client = ClockifyClient::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => 'client-1',
        'name' => 'Historic Co',
    ]);
    $client->delete();

    expect(ClockifyClient::withTrashed()->find($client->id)?->name)->toBe('Historic Co');
});
