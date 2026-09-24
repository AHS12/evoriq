<?php

use App\Enums\DataProcessingJobStatus;
use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use App\Models\DataProcessingJob;
use App\Models\Notification;
use App\Models\User;

test('marks an import failed and notifies the owner when the job fails', function () {
    $owner = User::factory()->create();

    $job = DataProcessingJob::factory()->active()->import()->create([
        'user_id' => $owner->id,
    ]);

    (new ProcessImport($job))->failed(new RuntimeException('boom'));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::FAILED)
        ->and($job->error_message)->toBe('boom')
        ->and(Notification::where('type', 'import.failed')->exists())->toBeTrue();
});

test('marks an export failed when the job fails', function () {
    $job = DataProcessingJob::factory()->active()->create();

    (new ProcessExport($job))->failed(new RuntimeException('kaboom'));

    expect($job->refresh()->status)->toBe(DataProcessingJobStatus::FAILED);
});

test('leaves a terminal job untouched when a late failure arrives', function () {
    $job = DataProcessingJob::factory()->completed()->create();

    (new ProcessExport($job))->failed(new RuntimeException('late'));

    expect($job->refresh()->status)->toBe(DataProcessingJobStatus::COMPLETED);
});
