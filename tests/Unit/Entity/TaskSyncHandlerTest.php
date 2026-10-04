<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyProject;
use App\Models\ClockifyTask;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\TaskSyncHandler;
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
function fetchAndUpsertTasks(ClockifyWorkspace $workspace, int $pageSize = 200): void
{
    $handler = app(TaskSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace, pageSize: $pageSize);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

function makeTasksWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create(['currency' => 'USD']);
}

function makeTaskProject(ClockifyWorkspace $workspace, string $clockifyId): ClockifyProject
{
    return ClockifyProject::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

function makeTaskUser(ClockifyWorkspace $workspace, string $clockifyId): ClockifyUser
{
    return ClockifyUser::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function taskPayload(string $id, string $projectId, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'name' => 'Task '.$id,
        'projectId' => $projectId,
        'status' => 'ACTIVE',
        'billable' => true,
    ], $overrides);
}

test('the task handler is registered for the tasks entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::TASKS))->toBeTrue();
});

test('it paginates tasks per project and resolves the project', function () {
    $workspace = makeTasksWorkspace();
    $project1 = makeTaskProject($workspace, 'project-1');
    $project2 = makeTaskProject($workspace, 'project-2');

    Http::fake(function ($request) {
        $url = $request->url();
        $page = (int) ($request['page'] ?? 1);

        if (str_contains($url, '/projects/project-1/tasks')) {
            return $page === 1
                ? Http::response([taskPayload('task-1', 'project-1'), taskPayload('task-2', 'project-1')], 200)
                : Http::response([taskPayload('task-3', 'project-1')], 200);
        }

        if (str_contains($url, '/projects/project-2/tasks')) {
            return Http::response([taskPayload('task-4', 'project-2')], 200);
        }

        return Http::response([], 200);
    });

    fetchAndUpsertTasks($workspace, pageSize: 2);

    expect(ClockifyTask::query()->count())->toBe(4)
        ->and(ClockifyTask::query()->where('clockify_id', 'task-1')->firstOrFail()->project_id)->toBe($project1->id)
        ->and(ClockifyTask::query()->where('clockify_id', 'task-3')->firstOrFail()->project_id)->toBe($project1->id)
        ->and(ClockifyTask::query()->where('clockify_id', 'task-4')->firstOrFail()->project_id)->toBe($project2->id);
});

test('it parses ISO-8601 durations into estimated hours', function () {
    $workspace = makeTasksWorkspace();
    makeTaskProject($workspace, 'project-1');

    Http::fake(['*' => Http::response([
        taskPayload('task-1', 'project-1', ['estimate' => ['estimate' => 'PT4H30M', 'type' => 'MANUAL']]),
        taskPayload('task-2', 'project-1', ['duration' => 'PT2H']),
        taskPayload('task-3', 'project-1', ['estimatedHours' => 6]),
    ], 200)]);

    fetchAndUpsertTasks($workspace);

    expect((float) ClockifyTask::query()->where('clockify_id', 'task-1')->value('estimated_hours'))->toBe(4.5)
        ->and((float) ClockifyTask::query()->where('clockify_id', 'task-2')->value('estimated_hours'))->toBe(2.0)
        ->and((float) ClockifyTask::query()->where('clockify_id', 'task-3')->value('estimated_hours'))->toBe(6.0);
});

test('it resolves the assignee from a single id or a list', function () {
    $workspace = makeTasksWorkspace();
    makeTaskProject($workspace, 'project-1');
    $single = makeTaskUser($workspace, 'user-1');
    $listed = makeTaskUser($workspace, 'user-2');

    Http::fake(['*' => Http::response([
        taskPayload('task-1', 'project-1', ['assigneeId' => 'user-1']),
        taskPayload('task-2', 'project-1', ['assigneeIds' => ['user-2']]),
    ], 200)]);

    fetchAndUpsertTasks($workspace);

    expect(ClockifyTask::query()->where('clockify_id', 'task-1')->firstOrFail()->assignee_user_id)->toBe($single->id)
        ->and(ClockifyTask::query()->where('clockify_id', 'task-2')->firstOrFail()->assignee_user_id)->toBe($listed->id);
});

test('re-syncing is idempotent', function () {
    $workspace = makeTasksWorkspace();
    makeTaskProject($workspace, 'project-1');

    Http::fake(['*' => Http::response([taskPayload('task-1', 'project-1')], 200)]);

    fetchAndUpsertTasks($workspace);
    fetchAndUpsertTasks($workspace);

    expect(ClockifyTask::query()->count())->toBe(1);
});

test('deleting a task soft-deletes it and a re-sync restores it', function () {
    $workspace = makeTasksWorkspace();
    makeTaskProject($workspace, 'project-1');

    Http::fake(['*' => Http::response([taskPayload('task-1', 'project-1')], 200)]);

    fetchAndUpsertTasks($workspace);

    $context = new SyncContext($workspace->connection, $workspace);
    app(TaskSyncHandler::class)->delete($context, 'task-1');

    expect(ClockifyTask::query()->count())->toBe(0)
        ->and(ClockifyTask::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();

    fetchAndUpsertTasks($workspace);

    expect(ClockifyTask::query()->count())->toBe(1)
        ->and(ClockifyTask::query()->firstOrFail()->deleted_at)->toBeNull();
});
