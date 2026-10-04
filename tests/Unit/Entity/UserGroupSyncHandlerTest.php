<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserGroup;
use App\Models\ClockifyUserGroupMember;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\UserGroupSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function makeGroupsWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

function makeGroupUser(ClockifyWorkspace $workspace, string $clockifyId): ClockifyUser
{
    return ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

function fetchAndUpsertUserGroups(ClockifyWorkspace $workspace): void
{
    $handler = app(UserGroupSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

/**
 * @param  array<int, string>  $userIds
 * @return array<string, mixed>
 */
function userGroupPayload(string $id, array $userIds, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'name' => 'Engineering',
        'status' => 'ACTIVE',
        'teamManagers' => ['user-1'],
        'userIds' => $userIds,
    ], $overrides);
}

test('the user group handler is registered', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::USER_GROUPS))->toBeTrue();
});

test('it persists groups, managers and resolved members', function () {
    $workspace = makeGroupsWorkspace();
    $user1 = makeGroupUser($workspace, 'user-1');
    $user2 = makeGroupUser($workspace, 'user-2');

    Http::fake(['*' => Http::response([
        userGroupPayload('group-1', ['user-1', 'user-2', 'missing']),
    ], 200)]);

    fetchAndUpsertUserGroups($workspace);

    $group = ClockifyUserGroup::query()->firstOrFail();

    expect($group->name)->toBe('Engineering')
        ->and($group->status)->toBe('ACTIVE')
        ->and($group->team_managers)->toBe(['user-1'])
        ->and($group->raw_data)->toBeArray();

    $memberIds = ClockifyUserGroupMember::query()
        ->where('user_group_id', $group->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($memberIds)->toBe(collect([$user1->id, $user2->id])->sort()->values()->all());
});

test('removing a member reflects after re-sync', function () {
    $workspace = makeGroupsWorkspace();
    makeGroupUser($workspace, 'user-1');
    makeGroupUser($workspace, 'user-2');

    Http::fakeSequence('*')
        ->push([userGroupPayload('group-1', ['user-1', 'user-2'])], 200)
        ->push([userGroupPayload('group-1', ['user-1'])], 200);

    fetchAndUpsertUserGroups($workspace);
    expect(ClockifyUserGroupMember::query()->count())->toBe(2);

    fetchAndUpsertUserGroups($workspace);

    expect(ClockifyUserGroup::query()->count())->toBe(1)
        ->and(ClockifyUserGroupMember::query()->count())->toBe(1);
});

test('unknown user ids are skipped without failing', function () {
    $workspace = makeGroupsWorkspace();
    makeGroupUser($workspace, 'user-1');

    Http::fake(['*' => Http::response([
        userGroupPayload('group-1', ['user-1', 'ghost']),
    ], 200)]);

    fetchAndUpsertUserGroups($workspace);

    expect(ClockifyUserGroupMember::query()->count())->toBe(1);
});

test('an absent userIds key leaves members untouched', function () {
    $workspace = makeGroupsWorkspace();
    makeGroupUser($workspace, 'user-1');

    $withMembers = userGroupPayload('group-1', ['user-1']);
    $withoutMembers = userGroupPayload('group-1', [], ['name' => 'Renamed']);
    unset($withoutMembers['userIds']);

    Http::fakeSequence('*')
        ->push([$withMembers], 200)
        ->push([$withoutMembers], 200);

    fetchAndUpsertUserGroups($workspace);
    fetchAndUpsertUserGroups($workspace);

    expect(ClockifyUserGroup::query()->firstOrFail()->name)->toBe('Renamed')
        ->and(ClockifyUserGroupMember::query()->count())->toBe(1);
});

test('deleting a group soft-deletes it and a re-sync restores it', function () {
    $workspace = makeGroupsWorkspace();
    makeGroupUser($workspace, 'user-1');

    Http::fake(['*' => Http::response([userGroupPayload('group-1', ['user-1'])], 200)]);

    fetchAndUpsertUserGroups($workspace);

    $context = new SyncContext($workspace->connection, $workspace);
    app(UserGroupSyncHandler::class)->delete($context, 'group-1');

    expect(ClockifyUserGroup::query()->count())->toBe(0)
        ->and(ClockifyUserGroup::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();

    fetchAndUpsertUserGroups($workspace);

    expect(ClockifyUserGroup::query()->count())->toBe(1)
        ->and(ClockifyUserGroup::query()->firstOrFail()->deleted_at)->toBeNull()
        ->and(ClockifyUserGroupMember::query()->count())->toBe(1);
});
