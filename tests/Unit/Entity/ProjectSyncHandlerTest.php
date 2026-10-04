<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyClient;
use App\Models\ClockifyConnection;
use App\Models\ClockifyProject;
use App\Models\ClockifyProjectMember;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\ProjectSyncHandler;
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
 * Fetch → map → idempotent upsert exactly as the runner would (ENT-00).
 */
function fetchAndUpsertProjects(ClockifyWorkspace $workspace): void
{
    $handler = app(ProjectSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

function makeProjectsWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create(['currency' => 'USD']);
}

function makeEntityUser(ClockifyWorkspace $workspace, string $clockifyId): ClockifyUser
{
    return ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

function makeEntityClient(ClockifyWorkspace $workspace, string $clockifyId): ClockifyClient
{
    return ClockifyClient::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $memberships
 * @return array<string, mixed>
 */
function projectPayload(array $memberships, array $overrides = []): array
{
    return array_replace([
        'id' => 'project-1',
        'name' => 'Website Redesign',
        'clientId' => 'client-1',
        'color' => '#ff0000',
        'note' => 'Important',
        'status' => 'ACTIVE',
        'archived' => false,
        'billable' => true,
        'public' => false,
        'hourlyRate' => ['amount' => 100, 'since' => '2026-01-01T00:00:00Z'],
        'costRate' => ['amount' => 50],
        'estimate' => ['estimate' => 'PT40H', 'type' => 'MANUAL'],
        'memberships' => $memberships,
    ], $overrides);
}

test('the project handler is registered for the projects entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::PROJECTS))->toBeTrue();
});

test('it persists project fields and resolves the client and members', function () {
    $workspace = makeProjectsWorkspace();
    $client = makeEntityClient($workspace, 'client-1');
    $user1 = makeEntityUser($workspace, 'user-1');
    $user2 = makeEntityUser($workspace, 'user-2');

    Http::fake(['*' => Http::response([projectPayload([
        ['userId' => 'user-1', 'membershipType' => 'PROJECT', 'membershipStatus' => 'ACTIVE', 'hourlyRate' => ['amount' => 120], 'costRate' => ['amount' => 60]],
        ['userId' => 'user-2', 'membershipType' => 'PROJECT', 'hourlyRate' => ['amount' => 110]],
    ])], 200)]);

    fetchAndUpsertProjects($workspace);

    $project = ClockifyProject::query()->firstOrFail();

    expect($project->name)->toBe('Website Redesign')
        ->and($project->client_id)->toBe($client->id)
        ->and($project->color)->toBe('#ff0000')
        ->and($project->note)->toBe('Important')
        ->and($project->billable)->toBeTrue()
        ->and($project->public)->toBeFalse()
        ->and((float) $project->billable_rate_amount)->toBe(100.0)
        ->and($project->billable_rate_currency)->toBe('USD')
        ->and((float) $project->cost_rate_amount)->toBe(50.0)
        ->and((float) $project->estimated_hours)->toBe(40.0)
        ->and($project->raw_data)->toBeArray();

    $members = ClockifyProjectMember::query()->orderBy('user_id')->get();

    expect($members)->toHaveCount(2)
        ->and($members->pluck('user_id')->all())->toBe([$user1->id, $user2->id])
        ->and((float) $members[0]->hourly_rate_amount)->toBe(120.0)
        ->and($members[0]->hourly_rate_currency)->toBe('USD')
        ->and((float) $members[0]->cost_rate_amount)->toBe(60.0)
        ->and($members->first()->membership_type)->toBe('project');
});

test('a missing client is tolerated and stored as null', function () {
    $workspace = makeProjectsWorkspace();

    Http::fake(['*' => Http::response([
        projectPayload([], ['clientId' => 'missing-client', 'id' => 'project-9']),
    ], 200)]);

    fetchAndUpsertProjects($workspace);

    expect(ClockifyProject::query()->firstOrFail()->client_id)->toBeNull();
});

test('re-syncing replaces the member set so removals are reflected', function () {
    $workspace = makeProjectsWorkspace();
    makeEntityClient($workspace, 'client-1');
    makeEntityUser($workspace, 'user-1');
    makeEntityUser($workspace, 'user-2');

    Http::fakeSequence('*')
        ->push([projectPayload([
            ['userId' => 'user-1', 'membershipType' => 'PROJECT'],
            ['userId' => 'user-2', 'membershipType' => 'PROJECT'],
        ])], 200)
        ->push([projectPayload([
            ['userId' => 'user-1', 'membershipType' => 'PROJECT'],
        ])], 200);

    fetchAndUpsertProjects($workspace);
    expect(ClockifyProjectMember::query()->count())->toBe(2);

    fetchAndUpsertProjects($workspace);

    expect(ClockifyProject::query()->count())->toBe(1)
        ->and(ClockifyProjectMember::query()->count())->toBe(1);
});

test('a payload without memberships leaves existing members untouched', function () {
    $workspace = makeProjectsWorkspace();
    makeEntityClient($workspace, 'client-1');
    makeEntityUser($workspace, 'user-1');

    $withMembers = projectPayload([['userId' => 'user-1', 'membershipType' => 'PROJECT']]);
    $withoutMembers = projectPayload([], ['name' => 'Renamed']);
    unset($withoutMembers['memberships']);

    Http::fakeSequence('*')
        ->push([$withMembers], 200)
        ->push([$withoutMembers], 200);

    fetchAndUpsertProjects($workspace);
    fetchAndUpsertProjects($workspace);

    expect(ClockifyProject::query()->firstOrFail()->name)->toBe('Renamed')
        ->and(ClockifyProjectMember::query()->count())->toBe(1);
});

test('deleting a project soft-deletes it and a re-sync restores it', function () {
    $workspace = makeProjectsWorkspace();

    Http::fake(['*' => Http::response([projectPayload([])], 200)]);

    fetchAndUpsertProjects($workspace);

    $context = new SyncContext($workspace->connection, $workspace);
    app(ProjectSyncHandler::class)->delete($context, 'project-1');

    expect(ClockifyProject::query()->count())->toBe(0)
        ->and(ClockifyProject::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();

    fetchAndUpsertProjects($workspace);

    expect(ClockifyProject::query()->count())->toBe(1)
        ->and(ClockifyProject::query()->firstOrFail()->deleted_at)->toBeNull();
});
