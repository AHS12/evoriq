<?php

use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyCustomField;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryCustomFieldValue;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\TimeEntryCfValueSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

function makeCfWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

function makeCfEntry(ClockifyWorkspace $workspace, string $clockifyId): ClockifyTimeEntry
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
        'clockify_id' => $clockifyId,
    ]);
}

function makeCfField(ClockifyWorkspace $workspace, string $clockifyId, string $type = 'TEXT'): ClockifyCustomField
{
    return ClockifyCustomField::factory()->create([
        'organization_id' => $workspace->organization_id,
        'workspace_id' => $workspace->id,
        'clockify_id' => $clockifyId,
        'type' => $type,
    ]);
}

function runCfValueSync(ClockifyWorkspace $workspace, string $userId): void
{
    $handler = app(TimeEntryCfValueSyncHandler::class);
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
 * @param  array<int, array<string, mixed>>  $values
 * @return array<string, mixed>
 */
function cfEntryPayload(string $id, array $values): array
{
    return [
        'id' => $id,
        'userId' => 'user-1',
        'timeInterval' => ['start' => '2026-05-01T09:00:00Z', 'end' => '2026-05-01T10:00:00Z'],
        'customFieldValues' => $values,
    ];
}

test('the custom field value handler is registered', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::TIME_ENTRY_CUSTOM_FIELD_VALUE))->toBeTrue();
});

test('it stores text, number and multi-select values', function () {
    $workspace = makeCfWorkspace();
    $entry = makeCfEntry($workspace, 'e1');
    $text = makeCfField($workspace, 'cf-1', 'DROPDOWN');
    $number = makeCfField($workspace, 'cf-2', 'NUMBER');
    $multi = makeCfField($workspace, 'cf-3', 'DROPDOWN_MULTIPLE');

    Http::fake(['*' => Http::response([cfEntryPayload('e1', [
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ['customFieldId' => 'cf-2', 'value' => 5],
        ['customFieldId' => 'cf-3', 'value' => ['A', 'B']],
    ])], 200)]);

    runCfValueSync($workspace, 'user-1');

    $valueOf = fn (int $fieldId) => ClockifyTimeEntryCustomFieldValue::query()
        ->where('time_entry_id', $entry->id)
        ->where('custom_field_id', $fieldId)
        ->firstOrFail()
        ->value;

    expect(ClockifyTimeEntryCustomFieldValue::query()->count())->toBe(3)
        ->and($valueOf($text->id))->toBe('Engineering')
        ->and($valueOf($number->id))->toBe(5)
        ->and($valueOf($multi->id))->toBe(['A', 'B']);
});

test('removed values disappear after re-sync', function () {
    $workspace = makeCfWorkspace();
    makeCfEntry($workspace, 'e1');
    $text = makeCfField($workspace, 'cf-1');
    $number = makeCfField($workspace, 'cf-2', 'NUMBER');

    Http::fakeSequence('*')
        ->push([cfEntryPayload('e1', [
            ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
            ['customFieldId' => 'cf-2', 'value' => 5],
        ])], 200)
        ->push([cfEntryPayload('e1', [
            ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ])], 200);

    runCfValueSync($workspace, 'user-1');
    expect(ClockifyTimeEntryCustomFieldValue::query()->count())->toBe(2);

    runCfValueSync($workspace, 'user-1');

    expect(ClockifyTimeEntryCustomFieldValue::query()->count())->toBe(1)
        ->and(ClockifyTimeEntryCustomFieldValue::query()->firstOrFail()->custom_field_id)->toBe($text->id)
        ->and(ClockifyTimeEntryCustomFieldValue::query()->where('custom_field_id', $number->id)->exists())->toBeFalse();
});

test('unknown custom fields are skipped without failing the entry', function () {
    $workspace = makeCfWorkspace();
    makeCfEntry($workspace, 'e1');
    makeCfField($workspace, 'cf-1');

    Http::fake(['*' => Http::response([cfEntryPayload('e1', [
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
        ['customFieldId' => 'missing', 'value' => 'x'],
    ])], 200)]);

    runCfValueSync($workspace, 'user-1');

    expect(ClockifyTimeEntryCustomFieldValue::query()->count())->toBe(1);
});

test('re-syncing custom field values is idempotent', function () {
    $workspace = makeCfWorkspace();
    makeCfEntry($workspace, 'e1');
    makeCfField($workspace, 'cf-1');

    Http::fake(['*' => Http::response([cfEntryPayload('e1', [
        ['customFieldId' => 'cf-1', 'value' => 'Engineering'],
    ])], 200)]);

    runCfValueSync($workspace, 'user-1');
    runCfValueSync($workspace, 'user-1');

    expect(ClockifyTimeEntryCustomFieldValue::query()->count())->toBe(1);
});
