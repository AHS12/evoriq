<?php

use App\Models\PipelineEvent;

test('the prune command removes old events and keeps recent ones', function () {
    config(['pipeline.event_retention_days' => 30]);

    $old = PipelineEvent::factory()->create(['occurred_at' => now()->subDays(45)]);
    $recent = PipelineEvent::factory()->create(['occurred_at' => now()->subDays(5)]);

    $this->artisan('pipeline:prune-events')
        ->expectsOutputToContain('Pruned 1 pipeline event')
        ->assertSuccessful();

    expect(PipelineEvent::query()->find($old->id))->toBeNull()
        ->and(PipelineEvent::query()->find($recent->id))->not->toBeNull();
});

test('the prune command supports a dry run', function () {
    config(['pipeline.event_retention_days' => 30]);

    PipelineEvent::factory()->create(['occurred_at' => now()->subDays(45)]);

    $this->artisan('pipeline:prune-events', ['--dry-run' => true])
        ->expectsOutputToContain('Would prune 1 pipeline event')
        ->assertSuccessful();

    expect(PipelineEvent::query()->count())->toBe(1);
});

test('the prune command honours an explicit day override', function () {
    PipelineEvent::factory()->create(['occurred_at' => now()->subDays(10)]);

    $this->artisan('pipeline:prune-events', ['--days' => 5])
        ->expectsOutputToContain('Pruned 1 pipeline event')
        ->assertSuccessful();

    expect(PipelineEvent::query()->count())->toBe(0);
});
