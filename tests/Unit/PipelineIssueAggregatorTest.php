<?php

use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Services\Pipeline\PipelineIssueAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function issueEvent(array $attributes = []): PipelineEvent
{
    return new PipelineEvent(array_merge([
        'run_type' => 'data_processing',
        'run_id' => '1',
        'sequence' => 1,
        'type' => 'info',
        'level' => 'info',
        'attempt' => 1,
        'occurred_at' => now(),
    ], $attributes));
}

test('groups stored import errors by type and message', function () {
    $job = DataProcessingJob::factory()->make([
        'errors' => [
            ['row' => 2, 'type' => 'duplicate', 'message' => 'Duplicate email'],
            ['row' => 5, 'type' => 'duplicate', 'message' => 'Duplicate email'],
            ['row' => 9, 'type' => 'validation', 'message' => 'Invalid email'],
        ],
    ]);

    $groups = (new PipelineIssueAggregator)->summarize($job, new Collection);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->type)->toBe('duplicate')
        ->and($groups[0]->count)->toBe(2)
        ->and($groups[0]->sampleRows)->toHaveCount(2)
        ->and($groups[1]->type)->toBe('validation')
        ->and($groups[1]->count)->toBe(1);
});

test('caps sample rows per group', function () {
    $errors = array_map(
        static fn (int $row): array => ['row' => $row, 'type' => 'duplicate', 'message' => 'Dup'],
        range(1, 15),
    );

    $job = DataProcessingJob::factory()->make(['errors' => $errors]);

    $groups = (new PipelineIssueAggregator)->summarize($job, new Collection);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->count)->toBe(15)
        ->and($groups[0]->sampleRows)->toHaveCount(10);
});

test('caps the number of groups at fifty', function () {
    $errors = array_map(
        static fn (int $index): array => ['row' => $index, 'type' => 'error', 'message' => "message {$index}"],
        range(1, 60),
    );

    $job = DataProcessingJob::factory()->make(['errors' => $errors]);

    expect((new PipelineIssueAggregator)->summarize($job, new Collection))
        ->toHaveCount(50);
});

test('falls back to error and warning events when no stored errors exist', function () {
    $job = DataProcessingJob::factory()->make(['errors' => []]);

    $events = new Collection([
        issueEvent(['sequence' => 1, 'level' => 'error', 'type' => 'error', 'message' => 'Boom']),
        issueEvent(['sequence' => 2, 'level' => 'warning', 'type' => 'warning', 'message' => 'Careful']),
        issueEvent(['sequence' => 3, 'level' => 'info', 'type' => 'info', 'message' => 'ok']),
    ]);

    $groups = (new PipelineIssueAggregator)->summarize($job, $events);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->message)->toBe('Boom')
        ->and($groups[0]->firstSeen)->not->toBeNull()
        ->and($groups[1]->message)->toBe('Careful');
});

test('records first and last seen from matching events', function () {
    $job = DataProcessingJob::factory()->make([
        'errors' => [['row' => 1, 'type' => 'error', 'message' => 'Boom']],
    ]);

    $events = new Collection([
        issueEvent(['sequence' => 1, 'level' => 'error', 'type' => 'error', 'message' => 'Boom', 'occurred_at' => now()->subMinutes(5)]),
        issueEvent(['sequence' => 2, 'level' => 'error', 'type' => 'error', 'message' => 'Boom', 'occurred_at' => now()]),
    ]);

    $groups = (new PipelineIssueAggregator)->summarize($job, $events);

    expect($groups[0]->firstSeen)->not->toBeNull()
        ->and($groups[0]->lastSeen)->not->toBeNull()
        ->and($groups[0]->count)->toBe(1);
});
