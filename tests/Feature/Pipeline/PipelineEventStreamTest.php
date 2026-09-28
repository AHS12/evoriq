<?php

use App\Enums\ExportFormat;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Mock\ExportMockData;

function pipelineEventsFor(DataProcessingJob $job): Collection
{
    return PipelineEvent::query()
        ->where('run_type', PipelineRunType::DATA_PROCESSING->value)
        ->where('run_id', (string) $job->getKey())
        ->orderBy('sequence')
        ->get();
}

test('queuing a job records a dispatched event', function () {
    Queue::fake();

    $this->actingAs(superAdmin())
        ->postJson(route('exports.store'), ExportMockData::request())
        ->assertCreated();

    $job = DataProcessingJob::query()->latest('id')->firstOrFail();

    expect(pipelineEventsFor($job)->pluck('type')->all())
        ->toContain(PipelineEventType::DISPATCHED);
});

test('a completed export writes a gapless, terminal event stream', function () {
    Storage::fake('local');

    User::factory()->count(2)->create();

    $job = DataProcessingJob::factory()->create(['format' => ExportFormat::CSV]);

    (new ProcessExport($job))->handle(app(DataProcessingJobService::class));

    $events = pipelineEventsFor($job);

    expect($events)->not->toBeEmpty()
        ->and($events->pluck('sequence')->all())->toBe(range(1, $events->count()))
        ->and($events->pluck('type')->all())->toContain(PipelineEventType::STARTED)
        ->and($events->pluck('type')->all())->toContain(PipelineEventType::ARTIFACT_READY)
        ->and($events->last()->type)->toBe(PipelineEventType::COMPLETED)
        ->and($events->last()->level->value)->toBe('success')
        ->and($events->where('type', PipelineEventType::COMPLETED)->count())->toBe(1);
});

test('a large import coalesces progress events', function () {
    Storage::fake('local');
    config([
        'pipeline.progress_event_min_seconds' => 3600,
        'exports.import.chunk_size' => 10,
    ]);

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

    $events = pipelineEventsFor($job);

    $progressCount = $events->where('type', PipelineEventType::PROGRESS)->count();

    expect($events->pluck('type')->all())->toContain(PipelineEventType::STAGE_STARTED)
        ->and($events->last()->type)->toBe(PipelineEventType::COMPLETED)
        // 52 chunk callbacks collapse into ~one event per 5% bucket.
        ->and($progressCount)->toBeGreaterThanOrEqual(5)
        ->and($progressCount)->toBeLessThan(30);
});

test('a failed run ends with an error and a single terminal failed event', function () {
    $job = DataProcessingJob::factory()->active()->create(['entity_type' => null]);

    $export = new ProcessExport($job);

    try {
        $export->handle(app(DataProcessingJobService::class));
    } catch (RuntimeException $e) {
        $export->failed($e);
    }

    $events = pipelineEventsFor($job);

    $terminals = $events->filter(fn (PipelineEvent $event): bool => $event->type->isTerminal());

    expect($events->pluck('type')->all())->toContain(PipelineEventType::ERROR)
        ->and($events->last()->type)->toBe(PipelineEventType::FAILED)
        ->and($terminals)->toHaveCount(1);
});
