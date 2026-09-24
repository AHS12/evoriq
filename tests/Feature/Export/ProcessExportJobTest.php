<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Support\Facades\Storage;

test('processes an export job and stores the generated file', function () {
    Storage::fake('local');

    $job = DataProcessingJob::factory()->create([
        'format' => ExportFormat::CSV,
    ]);

    (new ProcessExport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::COMPLETED)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $job->file_path);
});

test('marks the job failed when the entity type is missing', function () {
    Storage::fake('local');

    $job = DataProcessingJob::factory()->create(['entity_type' => null]);
    $export = new ProcessExport($job);

    try {
        $export->handle(app(DataProcessingJobService::class));
    } catch (RuntimeException $e) {
        $export->failed($e);
    }

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::FAILED)
        ->and($job->error_message)->toContain('missing an entity type');
});

test('records the total row count as progress', function () {
    Storage::fake('local');

    User::factory()->count(3)->create();

    $job = DataProcessingJob::factory()->create(['format' => ExportFormat::CSV]);

    $count = User::query()->count();

    (new ProcessExport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->total_items)->toBe($count)
        ->and($job->processed_items)->toBe($count)
        ->and($job->progressPercentage())->toBe(100);
});

test('notifies the owner when an export completes', function () {
    Storage::fake('local');

    $owner = User::factory()->create();

    $job = DataProcessingJob::factory()->create([
        'format' => ExportFormat::CSV,
        'user_id' => $owner->id,
    ]);

    (new ProcessExport($job))->handle(app(DataProcessingJobService::class));

    expect(Notification::where('type', 'export.completed')->exists())->toBeTrue();
});
