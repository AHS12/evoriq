<?php

use App\Enums\DataProcessingJobStatus;
use App\Jobs\ProcessImport;
use App\Models\DataProcessingJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Support\Facades\Storage;
use Tests\Mock\ImportMockData;

test('imports users, skips duplicates and stores a report', function () {
    Storage::fake('local');

    $owner = User::factory()->create();

    Storage::disk('local')->put('exports/imports/users.csv', ImportMockData::csv());

    $job = DataProcessingJob::factory()->import()->create([
        'user_id' => $owner->id,
        'input_path' => 'exports/imports/users.csv',
        'input_disk' => 'local',
        'filters' => ['send_invitations' => false],
    ]);

    (new ProcessImport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::COMPLETED)
        ->and($job->processed_items)->toBe(4)
        ->and($job->success_count)->toBe(2)
        ->and($job->error_count)->toBe(1)
        ->and($job->file_path)->not->toBeNull()
        ->and(User::where('email', 'ada@example.com')->count())->toBe(1)
        ->and(User::where('email', 'grace@example.com')->count())->toBe(1);

    Storage::disk('local')->assertExists((string) $job->file_path);
});

test('notifies the owner when an import completes', function () {
    Storage::fake('local');

    $owner = User::factory()->create();

    Storage::disk('local')->put('exports/imports/users.csv', ImportMockData::csv());

    $job = DataProcessingJob::factory()->import()->create([
        'user_id' => $owner->id,
        'input_path' => 'exports/imports/users.csv',
        'input_disk' => 'local',
        'filters' => ['send_invitations' => false],
    ]);

    (new ProcessImport($job))->handle(app(DataProcessingJobService::class));

    expect(Notification::where('type', 'import.completed')->exists())->toBeTrue();
});

test('cancels an import that was cancelled before it started', function () {
    Storage::fake('local');

    Storage::disk('local')->put('exports/imports/users.csv', ImportMockData::csv());

    $job = DataProcessingJob::factory()->import()->create([
        'input_path' => 'exports/imports/users.csv',
        'input_disk' => 'local',
        'cancel_requested_at' => now(),
    ]);

    (new ProcessImport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::CANCELLED)
        ->and(User::where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('marks the import failed when the entity type is missing', function () {
    Storage::fake('local');

    Storage::disk('local')->put('exports/imports/users.csv', ImportMockData::csv());

    $job = DataProcessingJob::factory()->import()->create([
        'entity_type' => null,
        'input_path' => 'exports/imports/users.csv',
        'input_disk' => 'local',
    ]);

    $import = new ProcessImport($job);

    try {
        $import->handle(app(DataProcessingJobService::class));
    } catch (RuntimeException $e) {
        $import->failed($e);
    }

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::FAILED);
});

test('imports a large file across chunk boundaries', function () {
    Storage::fake('local');

    $rows = ['Name,Email,Roles'];

    for ($i = 1; $i <= 520; $i++) {
        $rows[] = "User {$i},user{$i}@example.com,";
    }

    Storage::disk('local')->put('exports/imports/large.csv', implode("\n", $rows));

    $job = DataProcessingJob::factory()->import()->create([
        'input_path' => 'exports/imports/large.csv',
        'input_disk' => 'local',
        'filters' => ['send_invitations' => false],
    ]);

    (new ProcessImport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::COMPLETED)
        ->and($job->total_items)->toBe(520)
        ->and($job->processed_items)->toBe(520)
        ->and($job->success_count)->toBe(520)
        ->and(User::where('email', 'like', 'user%@example.com')->count())->toBe(520);
});
