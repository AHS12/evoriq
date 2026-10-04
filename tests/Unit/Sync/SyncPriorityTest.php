<?php

use App\Enums\QueueName;
use App\Enums\SyncPriority;
use App\Jobs\Sync\SyncEntityJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('priority maps to a queue channel that preserves ordering', function () {
    expect(SyncPriority::HIGH->queue())->toBe(QueueName::CRITICAL)
        ->and(SyncPriority::NORMAL->queue())->toBe(QueueName::DEFAULT)
        ->and(SyncPriority::LOW->queue())->toBe(QueueName::HEAVY);
});

test('higher levels are the strictly-higher priorities', function () {
    expect(SyncPriority::HIGH->higherLevels())->toBe([])
        ->and(SyncPriority::NORMAL->higherLevels())->toBe([SyncPriority::HIGH])
        ->and(SyncPriority::LOW->higherLevels())->toBe([SyncPriority::HIGH, SyncPriority::NORMAL]);
});

test('a sync job dispatches on its priority channel with self-managed retries', function () {
    $high = new SyncEntityJob(1, SyncPriority::HIGH->value);
    $low = new SyncEntityJob(2, SyncPriority::LOW->value);
    $default = new SyncEntityJob(3);

    expect($high->queue)->toBe(QueueName::CRITICAL->value)
        ->and($low->queue)->toBe(QueueName::HEAVY->value)
        ->and($default->queue)->toBe(QueueName::DEFAULT->value)
        ->and($high->tries)->toBe(1)
        ->and($low->tries)->toBe(1);
});
