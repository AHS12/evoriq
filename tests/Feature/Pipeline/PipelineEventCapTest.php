<?php

use App\Enums\PipelineEventType;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Pipeline\PipelineEventRecorder;

test('a run never exceeds its event cap and keeps important events', function () {
    config(['pipeline.max_events_per_run' => 3]);

    $job = DataProcessingJob::factory()->create();
    $recorder = app(PipelineEventRecorder::class);

    // Three distinct-stage progress events reach the cap.
    $recorder->progress($job, 1, 100, 'stage-1');
    $recorder->progress($job, 2, 100, 'stage-2');
    $recorder->progress($job, 3, 100, 'stage-3');

    // Further progress is dropped, with a single cap warning.
    $recorder->progress($job, 4, 100, 'stage-4');
    $recorder->progress($job, 5, 100, 'stage-5');

    // Terminal/stage events are never dropped.
    $recorder->completed($job);

    $events = PipelineEvent::query()
        ->forRun($job->pipelineRunType(), $job->pipelineRunId())
        ->get();

    $progress = $events->where('type', PipelineEventType::PROGRESS);
    $warnings = $events->where('type', PipelineEventType::WARNING);
    $completed = $events->where('type', PipelineEventType::COMPLETED);

    expect($progress)->toHaveCount(3)
        ->and($warnings)->toHaveCount(1)
        ->and($warnings->first()->context['event_cap'])->toBeTrue()
        ->and($completed)->toHaveCount(1);
});

test('deleting a run cascades its pipeline events', function () {
    $job = DataProcessingJob::factory()->create();

    PipelineEvent::factory()->count(2)->create([
        'run_type' => $job->pipelineRunType(),
        'run_id' => $job->pipelineRunId(),
    ]);

    expect(PipelineEvent::query()->forRun($job->pipelineRunType(), $job->pipelineRunId())->count())
        ->toBe(2);

    app(DataProcessingJobService::class)->delete($job);

    expect(PipelineEvent::query()->forRun($job->pipelineRunType(), $job->pipelineRunId())->count())
        ->toBe(0);
});
