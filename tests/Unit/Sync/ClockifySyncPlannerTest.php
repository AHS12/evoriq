<?php

use App\DTOs\Sync\PlanRequest;
use App\DTOs\Sync\WorkspaceInspection;
use App\Enums\ApiUsageWindowType;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\ApiUsageService;
use App\Services\Sync\ClockifySyncPlanner;
use App\Services\Sync\WorkspaceInspector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $this->inspector = Mockery::mock(WorkspaceInspector::class);
    $this->planner = new ClockifySyncPlanner($this->inspector, app(ApiUsageService::class));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Mockery::close();
});

/**
 * @param  array<int, SyncEntityType>|null  $entities
 */
function makePlanRequest(
    ClockifyConnection $connection,
    ClockifyWorkspace $workspace,
    string $start,
    string $end,
    ?array $entities = null,
): PlanRequest {
    return new PlanRequest(
        connection: $connection,
        workspace: $workspace,
        rangeStart: CarbonImmutable::parse($start),
        rangeEnd: CarbonImmutable::parse($end),
        entities: $entities,
    );
}

function makeWorkspace(ClockifyConnection $connection): ClockifyWorkspace
{
    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

test('the plan clamps the range to the history horizon and to now', function () {
    $connection = ClockifyConnection::factory()->create();

    $this->inspector->shouldReceive('inspect')->andReturn(new WorkspaceInspection);

    $plan = $this->planner->plan(makePlanRequest($connection, makeWorkspace($connection), '2010-01-01', '2030-01-01'));

    expect($plan->rangeEnd->toDateString())->toBe('2026-06-15')
        ->and($plan->rangeStart->toDateString())->toBe('2021-06-15');
});

test('reference entities load first and facts fan out per user', function () {
    $connection = ClockifyConnection::factory()->create();

    $this->inspector->shouldReceive('inspect')->andReturn(new WorkspaceInspection(users: [
        ['id' => 'u1', 'name' => 'Ada'],
        ['id' => 'u2', 'name' => 'Grace'],
    ]));

    $plan = $this->planner->plan(makePlanRequest($connection, makeWorkspace($connection), '2026-01-01', '2026-03-15'));

    expect($plan->phases[0]->phase)->toBe(SyncPhase::REFERENCE)
        ->and($plan->phases[1]->phase)->toBe(SyncPhase::FACT)
        ->and($plan->phases[0]->jobs)->toHaveCount(8);

    $timeEntryJobs = array_values(array_filter(
        $plan->jobs(),
        static fn ($job): bool => $job->entityType === SyncEntityType::TIME_ENTRY,
    ));

    // 2 users × 3 partitions (Jan 1 → Mar 15 at 31-day partitions).
    expect($timeEntryJobs)->toHaveCount(6)
        ->and($timeEntryJobs[0]->userId)->toBe('u1')
        ->and($plan->estimate->partitions)->toBe(3);
});

test('estimates use the connection real budget window', function () {
    $free = ClockifyConnection::factory()->free()->create();
    $paid = ClockifyConnection::factory()->create([
        'requests_per_hour' => null,
        'requests_per_second' => 50,
    ]);

    $this->inspector->shouldReceive('inspect')->andReturn(new WorkspaceInspection(users: [
        ['id' => 'u1', 'name' => null],
    ]));

    $freePlan = $this->planner->plan(makePlanRequest($free, makeWorkspace($free), '2026-01-01', '2026-02-01'));
    $paidPlan = $this->planner->plan(makePlanRequest($paid, makeWorkspace($paid), '2026-01-01', '2026-02-01'));

    expect($freePlan->estimate->windowType)->toBe(ApiUsageWindowType::HOUR)
        ->and($freePlan->estimate->requestsPerWindow)->toBe(30)
        ->and($freePlan->estimate->windowSeconds)->toBe(3600)
        ->and($freePlan->estimate->requests)->toBe(11)
        ->and($freePlan->estimate->estimatedSeconds)->toBe(3600)
        ->and($paidPlan->estimate->windowType)->toBe(ApiUsageWindowType::SECOND)
        ->and($paidPlan->estimate->windowSeconds)->toBe(1);
});

test('partitions shrink when the estimated volume is large', function () {
    config(['clockify.planner.partition_max_items' => 5000]);

    $connection = ClockifyConnection::factory()->create();

    $users = array_map(
        static fn (int $i): array => ['id' => "u{$i}", 'name' => null],
        range(1, 100),
    );

    $this->inspector->shouldReceive('inspect')->andReturn(new WorkspaceInspection(
        users: $users,
        volumes: [SyncEntityType::TIME_ENTRY->value => 50_000_000],
    ));

    $plan = $this->planner->plan(makePlanRequest($connection, makeWorkspace($connection), '2021-06-15', '2026-06-15'));

    // 31-day partitions (59) exceed the per-job budget; 14-day (131) do not.
    expect($plan->estimate->partitions)->toBe(131);
});

test('the plan is deterministic and serializable', function () {
    $connection = ClockifyConnection::factory()->create();

    $this->inspector->shouldReceive('inspect')->andReturn(new WorkspaceInspection(users: [
        ['id' => 'u1', 'name' => 'Ada'],
    ]));

    $first = $this->planner->plan(makePlanRequest($connection, makeWorkspace($connection), '2026-01-01', '2026-04-01'));
    $second = $this->planner->plan(makePlanRequest($connection, makeWorkspace($connection), '2026-01-01', '2026-04-01'));

    expect($first->toArray())->toBe($second->toArray())
        ->and($first->toArray())->toHaveKeys(['mode', 'phases', 'estimate', 'total_jobs']);
});
