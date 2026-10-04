<?php

use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Models\ClockifyConnection;
use App\Models\ClockifyCustomField;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\CustomFieldSyncHandler;
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

function makeCustomFieldsWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

function fetchAndUpsertCustomFields(ClockifyWorkspace $workspace): void
{
    $handler = app(CustomFieldSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

test('the custom field handler is registered', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::CUSTOM_FIELDS))->toBeTrue();
});

test('it persists definitions with types, allowed values and defaults', function () {
    $workspace = makeCustomFieldsWorkspace();

    Http::fake(['*' => Http::response([
        [
            'id' => 'cf-1',
            'name' => 'Department',
            'description' => 'Team the entry belongs to',
            'type' => 'DROPDOWN',
            'entityType' => 'TIMEENTRY',
            'status' => 'VISIBLE',
            'required' => true,
            'onlyAdminCanEdit' => true,
            'allowedValues' => ['Engineering', 'Sales', 'Support'],
            'placeholder' => 'Pick one',
            'workspaceDefaultValue' => 'Engineering',
            'projectDefaultValues' => ['project-1' => 'Sales'],
        ],
        [
            'id' => 'cf-2',
            'name' => 'Account',
            'type' => 'TEXT',
            'entityType' => 'USER',
            'status' => 'INACTIVE',
        ],
    ], 200)]);

    fetchAndUpsertCustomFields($workspace);

    $field = ClockifyCustomField::query()->where('clockify_id', 'cf-1')->firstOrFail();
    $userField = ClockifyCustomField::query()->where('clockify_id', 'cf-2')->firstOrFail();

    expect($field->name)->toBe('Department')
        ->and($field->description)->toBe('Team the entry belongs to')
        ->and($field->type)->toBe('DROPDOWN')
        ->and($field->entity_type)->toBe('TIMEENTRY')
        ->and($field->status)->toBe('VISIBLE')
        ->and($field->required)->toBeTrue()
        ->and($field->only_admin_can_edit)->toBeTrue()
        ->and($field->allowed_values)->toBe(['Engineering', 'Sales', 'Support'])
        ->and($field->placeholder)->toBe('Pick one')
        ->and($field->workspace_default_value)->toBe('Engineering')
        ->and($field->project_default_values)->toBe(['project-1' => 'Sales'])
        ->and($field->raw_data)->toBeArray()
        ->and($userField->entity_type)->toBe('USER')
        ->and($userField->status)->toBe('INACTIVE')
        ->and($userField->required)->toBeFalse()
        ->and($userField->allowed_values)->toBeNull();
});

test('the runner pages through every custom field', function () {
    $workspace = makeCustomFieldsWorkspace();

    $run = ClockifySyncRun::factory()->create([
        'connection_id' => $workspace->connection_id,
        'workspace_id' => $workspace->id,
    ]);
    $job = ClockifySyncJob::factory()->create([
        'sync_run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'entity_type' => SyncEntityType::CUSTOM_FIELDS,
        'phase' => SyncPhase::REFERENCE,
        'page_size' => 2,
    ]);

    Http::fake(fn ($request) => (int) ($request['page'] ?? 1) === 1
        ? Http::response([['id' => 'cf-1', 'name' => 'A', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY'], ['id' => 'cf-2', 'name' => 'B', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY']], 200)
        : Http::response([['id' => 'cf-3', 'name' => 'C', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY']], 200));

    app(SyncJobRunner::class)->run($job);

    expect(ClockifyCustomField::query()->count())->toBe(3);
});

test('re-syncing reflects a status change without duplicating', function () {
    $workspace = makeCustomFieldsWorkspace();

    Http::fakeSequence('*')
        ->push([['id' => 'cf-1', 'name' => 'Department', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY', 'status' => 'VISIBLE']], 200)
        ->push([['id' => 'cf-1', 'name' => 'Department', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY', 'status' => 'INACTIVE']], 200);

    fetchAndUpsertCustomFields($workspace);
    fetchAndUpsertCustomFields($workspace);

    expect(ClockifyCustomField::query()->count())->toBe(1)
        ->and(ClockifyCustomField::query()->firstOrFail()->status)->toBe('INACTIVE');
});

test('deleting a custom field soft-deletes and a re-sync restores it', function () {
    $workspace = makeCustomFieldsWorkspace();

    Http::fake(['*' => Http::response([['id' => 'cf-1', 'name' => 'Department', 'type' => 'TEXT', 'entityType' => 'TIMEENTRY']], 200)]);

    fetchAndUpsertCustomFields($workspace);

    $context = new SyncContext($workspace->connection, $workspace);
    app(CustomFieldSyncHandler::class)->delete($context, 'cf-1');

    expect(ClockifyCustomField::query()->count())->toBe(0)
        ->and(ClockifyCustomField::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();

    fetchAndUpsertCustomFields($workspace);

    expect(ClockifyCustomField::query()->count())->toBe(1)
        ->and(ClockifyCustomField::query()->firstOrFail()->deleted_at)->toBeNull();
});
