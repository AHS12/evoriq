<?php

use App\Models\ClockifyConnection;
use App\Models\ClockifyCustomField;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserCustomFieldValue;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\UserSyncHandler;
use App\Services\Sync\SyncContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function cfvWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

function cfvField(ClockifyWorkspace $workspace, string $clockifyId): ClockifyCustomField
{
    return ClockifyCustomField::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
        'entity_type' => 'USER',
    ]);
}

/**
 * Run the user sync exactly as the runner would (ENT-02), which derives user
 * custom field values (ENT-11) from the same payload.
 */
function runCfvUserSync(ClockifyWorkspace $workspace): void
{
    $handler = app(UserSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

/**
 * @param  array<int, array<string, mixed>>  $values
 * @return array<string, mixed>
 */
function cfvUserPayload(array $values): array
{
    return [
        'id' => 'user-1',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => 'ACTIVE',
        'customFieldValues' => $values,
    ];
}

test('user custom field values are derived during the user sync', function () {
    $workspace = cfvWorkspace();
    $department = cfvField($workspace, 'cf-1');
    $teams = cfvField($workspace, 'cf-2');

    Http::fake(['*' => Http::response([cfvUserPayload([
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ['customFieldId' => 'cf-2', 'value' => ['Alpha', 'Beta']],
    ])], 200)]);

    runCfvUserSync($workspace);

    $user = ClockifyUser::query()->firstOrFail();
    $valueOf = fn (int $fieldId) => ClockifyUserCustomFieldValue::query()
        ->where('user_id', $user->id)
        ->where('custom_field_id', $fieldId)
        ->firstOrFail()
        ->value;

    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(2)
        ->and($valueOf($department->id))->toBe('Engineering')
        ->and($valueOf($teams->id))->toBe(['Alpha', 'Beta']);
});

test('removed user values disappear after re-sync', function () {
    $workspace = cfvWorkspace();
    $department = cfvField($workspace, 'cf-1');
    cfvField($workspace, 'cf-2');

    Http::fakeSequence('*')
        ->push([cfvUserPayload([
            ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
            ['customFieldId' => 'cf-2', 'value' => ['Alpha']],
        ])], 200)
        ->push([cfvUserPayload([
            ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ])], 200);

    runCfvUserSync($workspace);
    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(2);

    runCfvUserSync($workspace);

    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(1)
        ->and(ClockifyUserCustomFieldValue::query()->firstOrFail()->custom_field_id)->toBe($department->id);
});

test('an absent customFieldValues key leaves existing values untouched', function () {
    $workspace = cfvWorkspace();
    cfvField($workspace, 'cf-1');

    $withValues = cfvUserPayload([['customFieldId' => 'cf-1', 'value' => 'Engineering']]);
    $withoutValues = cfvUserPayload([]);
    unset($withoutValues['customFieldValues']);

    Http::fakeSequence('*')
        ->push([$withValues], 200)
        ->push([$withoutValues], 200);

    runCfvUserSync($workspace);
    runCfvUserSync($workspace);

    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(1);
});

test('unknown custom fields are skipped', function () {
    $workspace = cfvWorkspace();
    cfvField($workspace, 'cf-1');

    Http::fake(['*' => Http::response([cfvUserPayload([
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ['customFieldId' => 'missing', 'value' => 'x'],
    ])], 200)]);

    runCfvUserSync($workspace);

    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(1);
});

test('re-syncing user custom field values is idempotent', function () {
    $workspace = cfvWorkspace();
    cfvField($workspace, 'cf-1');

    Http::fake(['*' => Http::response([cfvUserPayload([
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
    ])], 200)]);

    runCfvUserSync($workspace);
    runCfvUserSync($workspace);

    expect(ClockifyUserCustomFieldValue::query()->count())->toBe(1);
});
