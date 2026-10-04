<?php

namespace App\Services\Sync;

use App\DTOs\Pipeline\PipelineEventWindowDTO;
use App\DTOs\Sync\ImportPlan;
use App\DTOs\Sync\ImportPlanJob;
use App\DTOs\Sync\PlanRequest;
use App\Enums\AuditEvent;
use App\Enums\SyncTrigger;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Services\Audit\AuditLogService;

/**
 * Orchestrates the historical import wizard (PIPE-09, ENT-14): resolve the
 * active connection/workspace, build a plan for inspection, guard against
 * concurrent imports, start the umbrella run, and project it for the live
 * timeline. The controller stays thin; all coordination lives here.
 */
class ImportWizardService
{
    public function __construct(
        protected ClockifyConnectionRepositoryInterface $connections,
        protected ClockifyWorkspaceRepositoryInterface $workspaces,
        protected ClockifySyncPlanner $planner,
        protected SyncRunService $runs,
        protected ClockifySyncRunRepositoryInterface $runRepository,
        protected SyncRunPresenter $presenter,
        protected AuditLogService $audit,
    ) {}

    public function connection(): ?ClockifyConnection
    {
        return $this->connections->findActive();
    }

    public function workspace(ClockifyConnection $connection): ?ClockifyWorkspace
    {
        if ($connection->workspace_id === null) {
            return null;
        }

        return $this->workspaces->findByClockifyId($connection->id, $connection->workspace_id);
    }

    /**
     * The active import (non-final run), if any. Used as the concurrency guard
     * and to surface "resume/open" in the wizard.
     */
    public function activeRun(): ?ClockifySyncRun
    {
        return $this->runRepository->activeRun();
    }

    public function findRun(int $id): ?ClockifySyncRun
    {
        return $this->runRepository->findById($id, ['jobs']);
    }

    public function plan(PlanRequest $request): ImportPlan
    {
        return $this->planner->plan($request);
    }

    /**
     * Start the umbrella import run and audit the chosen range + entity set.
     * Callers must enforce the concurrency guard before calling.
     */
    public function start(PlanRequest $request, ImportPlan $plan): ClockifySyncRun
    {
        $run = $this->runs->start($request, SyncTrigger::INITIAL_IMPORT, $plan);

        $this->audit->record(
            AuditEvent::IMPORT_STARTED,
            $run,
            [
                'connection_id' => $run->connection_id,
                'range_start' => $run->range_start?->toIso8601String(),
                'range_end' => $run->range_end?->toIso8601String(),
                'entities' => array_values(array_unique(array_map(
                    static fn (ImportPlanJob $job): string => $job->entityType->value,
                    $plan->jobs(),
                ))),
                'jobs' => $run->total_jobs,
            ],
            actor: auth()->user(),
            description: __('Historical import started'),
        );

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ClockifySyncRun $run): array
    {
        return $this->presenter->present($run);
    }

    public function eventWindow(ClockifySyncRun $run, ?int $after = null, ?int $before = null): PipelineEventWindowDTO
    {
        return $this->presenter->eventWindow($run, $after, $before);
    }

    public function maxHistoryYears(): int
    {
        return max(1, (int) config('clockify.planner.max_history_years', 5));
    }
}
