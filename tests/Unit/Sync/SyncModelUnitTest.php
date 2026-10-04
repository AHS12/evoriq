<?php

use App\Enums\ApiUsageWindowType;
use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\ClockifyEntityChange;
use App\Models\ClockifyRawRecord;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a sync run casts its enums, dates and json plan', function () {
    $run = ClockifySyncRun::factory()->create([
        'trigger' => SyncTrigger::MANUAL,
        'mode' => SyncMode::INCREMENTAL,
        'priority' => SyncPriority::HIGH,
        'status' => SyncRunStatus::RUNNING,
        'plan' => ['phases' => []],
    ]);

    expect($run->trigger)->toBe(SyncTrigger::MANUAL)
        ->and($run->mode)->toBe(SyncMode::INCREMENTAL)
        ->and($run->priority)->toBe(SyncPriority::HIGH)
        ->and($run->status)->toBe(SyncRunStatus::RUNNING)
        ->and($run->plan)->toBe(['phases' => []])
        ->and($run->range_start)->toBeInstanceOf(CarbonInterface::class);
});

test('sync statuses report finality correctly', function () {
    expect(SyncRunStatus::COMPLETED->isFinal())->toBeTrue()
        ->and(SyncRunStatus::RUNNING->isFinal())->toBeFalse()
        ->and(SyncRunStatus::PAUSED->isActive())->toBeTrue()
        ->and(SyncJobStatus::FAILED->isFinal())->toBeTrue()
        ->and(SyncJobStatus::RETRY_SCHEDULED->isFinal())->toBeFalse()
        ->and(SyncJobStatus::RETRY_SCHEDULED->isActive())->toBeTrue();
});

test('a sync job casts its checkpoint and enum columns', function () {
    $job = ClockifySyncJob::factory()->create([
        'entity_type' => SyncEntityType::PROJECTS,
        'checkpoint' => ['cursor' => 'abc', 'last_changed_at' => '2026-01-01'],
    ]);

    expect($job->entity_type)->toBe(SyncEntityType::PROJECTS)
        ->and($job->checkpoint)->toBe(['cursor' => 'abc', 'last_changed_at' => '2026-01-01'])
        ->and($job->page)->toBeInt()
        ->and($job->isFinal())->toBeFalse();
});

test('entity changes and raw records cast their payloads', function () {
    $change = ClockifyEntityChange::factory()->create([
        'change_type' => EntityChangeType::DELETED,
        'raw_data' => ['id' => 'e1'],
    ]);

    $record = ClockifyRawRecord::factory()->create(['payload' => ['id' => 'x']]);

    expect($change->change_type)->toBe(EntityChangeType::DELETED)
        ->and($change->raw_data)->toBe(['id' => 'e1'])
        ->and($change->isProcessed())->toBeFalse()
        ->and($record->payload)->toBe(['id' => 'x'])
        ->and($record->entity_type)->toBe(SyncEntityType::TIME_ENTRY);
});

test('usage window types report their granularity', function () {
    expect(ApiUsageWindowType::HOUR->isHourly())->toBeTrue()
        ->and(ApiUsageWindowType::SECOND->isHourly())->toBeFalse()
        ->and(SyncEntityType::TIME_ENTRY->label())->toBeString();
});
