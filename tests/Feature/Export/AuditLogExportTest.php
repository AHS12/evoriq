<?php

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Jobs\ProcessExport;
use App\Models\AuditActivity;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Support\Facades\Storage;

test('queueing an audit log export requires the audit.export permission', function () {
    $this->actingAs(makeUserWithPermissions(['audit.view.all']));

    $this->post(route('exports.store'), [
        'entity_type' => DataEntity::AUDIT_LOGS->value,
        'format' => ExportFormat::CSV->value,
    ])->assertForbidden();
});

test('a user with audit.export can queue and an admin with export.create can too', function () {
    $this->actingAs(makeUserWithPermissions(['audit.export']));

    $this->post(route('exports.store'), [
        'entity_type' => DataEntity::AUDIT_LOGS->value,
        'format' => ExportFormat::CSV->value,
    ])->assertCreated();

    $this->actingAs(admin());

    $this->post(route('exports.store'), [
        'entity_type' => DataEntity::AUDIT_LOGS->value,
        'format' => ExportFormat::CSV->value,
    ])->assertCreated();
});

test('processes an audit log export honoring the filters', function () {
    Storage::fake('local');

    $actor = User::factory()->create();

    // Two domain rows and one auth row; the export filters to auth only.
    foreach (['domain', 'domain', 'auth'] as $channel) {
        AuditActivity::query()->create([
            'log_name' => $channel,
            'event' => 'updated',
            'description' => "Entry on {$channel}",
            'causer_type' => (new User)->getMorphClass(),
            'causer_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $job = DataProcessingJob::factory()->create([
        'entity_type' => DataEntity::AUDIT_LOGS,
        'format' => ExportFormat::CSV,
        'filters' => ['channel' => 'auth'],
    ]);

    (new ProcessExport($job))->handle(app(DataProcessingJobService::class));

    $job->refresh();

    expect($job->status)->toBe(DataProcessingJobStatus::COMPLETED)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->total_items)->toBe(1)
        ->and($job->processed_items)->toBe(1);

    Storage::disk('local')->assertExists((string) $job->file_path);

    $contents = Storage::disk('local')->get((string) $job->file_path);

    expect($contents)->toContain('Entry on auth')
        ->not->toContain('Entry on domain');
});
