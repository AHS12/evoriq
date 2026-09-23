<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
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

    expect(fn () => (new ProcessExport($job))->handle(app(DataProcessingJobService::class)))
        ->toThrow(RuntimeException::class);

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::FAILED)
        ->and($job->error_message)->toContain('missing an entity type');
});
