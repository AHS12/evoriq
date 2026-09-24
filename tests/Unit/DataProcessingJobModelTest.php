<?php

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Models\DataProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('displayName uses the stored name when present', function () {
    $job = new DataProcessingJob([
        'type' => DataProcessingJobType::EXPORT,
        'name' => 'Custom report',
    ]);

    expect($job->displayName())->toBe('Custom report');
});

test('displayName derives from entity and type', function () {
    $job = new DataProcessingJob([
        'type' => DataProcessingJobType::EXPORT,
        'entity_type' => DataEntity::USERS,
    ]);

    expect($job->displayName())->toBe('Users Export');
});

test('type helpers identify the operation', function () {
    expect((new DataProcessingJob(['type' => 'import']))->isImport())->toBeTrue()
        ->and((new DataProcessingJob(['type' => 'export']))->isExport())->toBeTrue()
        ->and((new DataProcessingJob(['type' => 'report']))->isReport())->toBeTrue();
});

test('isActive reflects non-final statuses', function () {
    expect((new DataProcessingJob(['status' => 'pending']))->isActive())->toBeTrue()
        ->and((new DataProcessingJob(['status' => 'processing']))->isActive())->toBeTrue()
        ->and((new DataProcessingJob(['status' => 'completed']))->isActive())->toBeFalse()
        ->and((new DataProcessingJob(['status' => 'cancelled']))->isActive())->toBeFalse();
});

test('cancellationRequested reflects the cancel flag', function () {
    expect((new DataProcessingJob(['cancel_requested_at' => now()]))->cancellationRequested())->toBeTrue()
        ->and((new DataProcessingJob)->cancellationRequested())->toBeFalse();
});

test('durationLabel formats short durations', function () {
    $job = new DataProcessingJob([
        'started_at' => now()->subSeconds(5),
        'completed_at' => now(),
    ]);

    expect($job->durationLabel())->toBe('5s');
});
