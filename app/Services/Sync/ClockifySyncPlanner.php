<?php

namespace App\Services\Sync;

use App\DTOs\Sync\ImportPlan;
use App\DTOs\Sync\ImportPlanEstimate;
use App\DTOs\Sync\ImportPlanJob;
use App\DTOs\Sync\ImportPlanPhase;
use App\DTOs\Sync\PlanRequest;
use App\DTOs\Sync\WorkspaceInspection;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use Carbon\CarbonInterface;

/**
 * Turns a requested range into ordered, budget-aware, resumable jobs (SYNC-03).
 *
 * Phases run reference dimensions → facts. Reference entities get one full
 * snapshot job each; time-entry facts fan out per active user × partition.
 * Partitions start at 31 days and shrink when a partition is estimated to
 * exceed the per-job item budget. The plan is deterministic and serializable.
 */
class ClockifySyncPlanner
{
    /**
     * Reference dimensions, parents before dependants.
     *
     * @var array<int, SyncEntityType>
     */
    private const REFERENCE_ORDER = [
        SyncEntityType::WORKSPACE,
        SyncEntityType::USER,
        SyncEntityType::CLIENTS,
        SyncEntityType::PROJECTS,
        SyncEntityType::TASKS,
        SyncEntityType::TAGS,
        SyncEntityType::CUSTOM_FIELDS,
        SyncEntityType::USER_GROUPS,
    ];

    /**
     * Fact entities that fan out per user × partition.
     *
     * @var array<int, SyncEntityType>
     */
    private const FACT_ORDER = [
        SyncEntityType::TIME_ENTRY,
        SyncEntityType::TIME_ENTRY_RATE,
        SyncEntityType::TIME_ENTRY_CUSTOM_FIELD_VALUE,
    ];

    public function __construct(
        protected WorkspaceInspector $inspector,
        protected ApiUsageService $usage,
    ) {}

    public function plan(PlanRequest $request): ImportPlan
    {
        [$start, $end] = $this->clampRange($request->rangeStart, $request->rangeEnd);

        $entities = $this->resolveEntities($request->entities);

        $inspection = $this->inspector->inspect(
            $request->connection,
            $request->workspace,
            $start,
            $end,
            $entities,
        );

        $rangeDays = max(1, (int) ceil($start->diffInDays($end)));
        $users = $inspection->users;
        $partitionDays = $this->partitionDays(
            $inspection->volume(SyncEntityType::TIME_ENTRY),
            $rangeDays,
            max(1, $inspection->userCount()),
        );
        $partitions = $this->partitions($start, $end, $partitionDays);

        $phases = [];

        $referenceJobs = $this->referenceJobs($entities, $inspection, $request);
        if ($referenceJobs !== []) {
            $phases[] = new ImportPlanPhase(SyncPhase::REFERENCE, $referenceJobs);
        }

        $factJobs = $this->factJobs($entities, $users, $partitions, $inspection, $request);
        if ($factJobs !== []) {
            $phases[] = new ImportPlanPhase(SyncPhase::FACT, $factJobs);
        }

        return new ImportPlan(
            mode: $request->mode,
            priority: $request->priority,
            rangeStart: $start,
            rangeEnd: $end,
            pageSize: $request->pageSize,
            phases: $phases,
            estimate: $this->estimate($request, $phases, count($partitions)),
        );
    }

    /**
     * Clamp the range to now and to the configured history horizon.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function clampRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $now = now();

        if ($end->greaterThan($now)) {
            $end = $now;
        }

        $floor = $now->copy()->subYears($this->maxHistoryYears());

        if ($start->lessThan($floor)) {
            $start = $floor;
        }

        if (! $start->lessThan($end)) {
            $start = $end->copy()->subDay();
        }

        return [$start, $end];
    }

    private function maxHistoryYears(): int
    {
        return max(1, (int) config('clockify.planner.max_history_years', 5));
    }

    /**
     * @param  array<int, SyncEntityType>|null  $entities
     * @return array<int, SyncEntityType>
     */
    private function resolveEntities(?array $entities): array
    {
        if ($entities === null || $entities === []) {
            return [...self::REFERENCE_ORDER, ...self::FACT_ORDER];
        }

        return array_values($entities);
    }

    /**
     * @param  array<int, SyncEntityType>  $entities
     * @return array<int, ImportPlanJob>
     */
    private function referenceJobs(array $entities, WorkspaceInspection $inspection, PlanRequest $request): array
    {
        $jobs = [];

        foreach (self::REFERENCE_ORDER as $entity) {
            if (! in_array($entity, $entities, true)) {
                continue;
            }

            $jobs[] = new ImportPlanJob(
                entityType: $entity,
                phase: SyncPhase::REFERENCE,
                priority: $request->priority,
                pageSize: $request->pageSize,
                estimatedRequests: $this->requestsForVolume($inspection->volume($entity), $request->pageSize),
            );
        }

        return $jobs;
    }

    /**
     * @param  array<int, SyncEntityType>  $entities
     * @param  array<int, array{id: string, name: string|null}>  $users
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $partitions
     * @return array<int, ImportPlanJob>
     */
    private function factJobs(
        array $entities,
        array $users,
        array $partitions,
        WorkspaceInspection $inspection,
        PlanRequest $request,
    ): array {
        if ($users === [] || $partitions === []) {
            return [];
        }

        $jobs = [];
        $userCount = count($users);
        $partitionCount = count($partitions);

        foreach (self::FACT_ORDER as $entity) {
            if (! in_array($entity, $entities, true)) {
                continue;
            }

            $estimated = $this->requestsForVolume(
                $this->perJobVolume($inspection->volume($entity), $userCount, $partitionCount),
                $request->pageSize,
            );

            foreach ($users as $user) {
                foreach ($partitions as [$partitionStart, $partitionEnd]) {
                    $jobs[] = new ImportPlanJob(
                        entityType: $entity,
                        phase: SyncPhase::FACT,
                        priority: $request->priority,
                        rangeStart: $partitionStart,
                        rangeEnd: $partitionEnd,
                        userId: $user['id'],
                        pageSize: $request->pageSize,
                        estimatedRequests: $estimated,
                    );
                }
            }
        }

        return $jobs;
    }

    /**
     * The partition size in days: the configured default, shrunk while a
     * partition's per-job volume exceeds the item budget.
     */
    private function partitionDays(?int $volume, int $rangeDays, int $users): int
    {
        $default = max(1, (int) config('clockify.planner.partition_days', 31));

        if ($volume === null || $volume <= 0) {
            return $default;
        }

        $maxItems = max(1, (int) config('clockify.planner.partition_max_items', 5000));

        foreach (array_unique([$default, 14, 7, 1]) as $days) {
            if ($days > $rangeDays) {
                continue;
            }

            $partitions = max(1, (int) ceil($rangeDays / $days));
            $perJob = (int) ceil($volume / max(1, $users * $partitions));

            if ($perJob <= $maxItems) {
                return $days;
            }
        }

        return 1;
    }

    /**
     * Split a range into fixed-size partitions (the final one may be shorter).
     *
     * @return array<int, array{0: CarbonInterface, 1: CarbonInterface}>
     */
    private function partitions(CarbonInterface $start, CarbonInterface $end, int $days): array
    {
        $partitions = [];
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $next = $cursor->copy()->addDays($days);

            if ($next->greaterThan($end)) {
                $next = $end->copy();
            }

            $partitions[] = [$cursor->copy(), $next->copy()];
            $cursor = $next;
        }

        if ($partitions === []) {
            $partitions[] = [$start->copy(), $end->copy()];
        }

        return $partitions;
    }

    private function requestsForVolume(?int $volume, int $pageSize): int
    {
        if ($volume === null || $volume <= 0) {
            return 1;
        }

        return max(1, (int) ceil($volume / max(1, $pageSize)));
    }

    private function perJobVolume(?int $volume, int $users, int $partitions): ?int
    {
        if ($volume === null) {
            return null;
        }

        return (int) ceil($volume / max(1, $users * $partitions));
    }

    /**
     * @param  array<int, ImportPlanPhase>  $phases
     */
    private function estimate(PlanRequest $request, array $phases, int $partitions): ImportPlanEstimate
    {
        $jobs = 0;
        $requests = 0;

        foreach ($phases as $phase) {
            $jobs += count($phase->jobs);
            $requests += $phase->estimatedRequests();
        }

        $snapshot = $this->usage->snapshot($request->connection, $request->workspace);
        $windowSeconds = $snapshot->windowType->isHourly() ? 3600 : 1;
        $requestsPerWindow = max(1, $snapshot->limit);
        $estimatedSeconds = (int) ceil($requests / $requestsPerWindow) * $windowSeconds;

        return new ImportPlanEstimate(
            jobs: $jobs,
            partitions: $partitions,
            requests: $requests,
            windowType: $snapshot->windowType,
            requestsPerWindow: $requestsPerWindow,
            windowSeconds: $windowSeconds,
            estimatedSeconds: $estimatedSeconds,
        );
    }
}
