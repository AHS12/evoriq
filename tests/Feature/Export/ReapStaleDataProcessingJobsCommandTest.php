<?php

use App\Enums\DataProcessingJobStatus;
use App\Models\DataProcessingJob;

test('reaps stale processing jobs', function () {
    $stale = DataProcessingJob::factory()->active()->create(['started_at' => now()->subHours(2)]);
    $fresh = DataProcessingJob::factory()->active()->create(['started_at' => now()->subMinutes(1)]);
    $completed = DataProcessingJob::factory()->completed()->create();

    $this->artisan('data-processing:reap-stale')->assertSuccessful();

    expect($stale->refresh()->status)->toBe(DataProcessingJobStatus::FAILED)
        ->and($fresh->refresh()->status)->toBe(DataProcessingJobStatus::PROCESSING)
        ->and($completed->refresh()->status)->toBe(DataProcessingJobStatus::COMPLETED);
});

test('does not change anything in a dry run', function () {
    $stale = DataProcessingJob::factory()->active()->create(['started_at' => now()->subHours(2)]);

    $this->artisan('data-processing:reap-stale', ['--dry-run' => true])->assertSuccessful();

    expect($stale->refresh()->status)->toBe(DataProcessingJobStatus::PROCESSING);
});
