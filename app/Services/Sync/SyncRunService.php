<?php

namespace App\Services\Sync;

use App\DTOs\Sync\ImportPlan;
use App\DTOs\Sync\PlanRequest;
use App\Enums\AuditEvent;
use App\Enums\SyncJobStatus;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Events\Sync\SyncRunFinished;
use App\Jobs\Sync\ResumeSyncRunJob;
use App\Jobs\Sync\SyncEntityJob;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a plan into a sync run and conducts it (SYNC-09): create the run + jobs,
 * dispatch them in budget/concurrency-bounded waves, aggregate progress, and
 * finalize. Interrupted runs resume without re-downloading completed pages
 * (the job engine checkpoints), and exhausted budgets defer to the window reset.
 */
class SyncRunService
{
    public function __construct(
        protected ClockifySyncPlanner $planner,
        protected ApiUsageService $usage,
        protected ClockifySyncRunRepositoryInterface $runs,
        protected ClockifySyncJobRepositoryInterface $jobs,
        protected AuditLogService $audit,
        protected SyncEventEmitter $events,
    ) {}

    /**
     * Plan (unless supplied) and start a run from a request.
     */
    public function start(
        PlanRequest $request,
        SyncTrigger $trigger = SyncTrigger::INITIAL_IMPORT,
        ?ImportPlan $plan = null,
    ): ClockifySyncRun {
        $plan ??= $this->planner->plan($request);

        return $this->startFromPlan($plan, $request->connection, $request->workspace, $trigger);
    }

    /**
     * Start a run from a pre-computed plan.
     */
    public function startFromPlan(
        ImportPlan $plan,
        ClockifyConnection $connection,
        ClockifyWorkspace $workspace,
        SyncTrigger $trigger = SyncTrigger::INITIAL_IMPORT,
    ): ClockifySyncRun {
        $run = $this->createRun($connection, $workspace, $plan, $trigger);

        $this->events->runQueued($run);

        $this->audit->record(
            AuditEvent::SYNC_RUN_STARTED,
            $run,
            ['connection_id' => $run->connection_id, 'jobs' => $run->total_jobs, 'trigger' => $run->trigger->value],
            actor: auth()->user(),
            description: __('Sync run started'),
        );

        $this->dispatchPending($run);

        return $run->refresh();
    }

    private function createRun(
        ClockifyConnection $connection,
        ClockifyWorkspace $workspace,
        ImportPlan $plan,
        SyncTrigger $trigger,
    ): ClockifySyncRun {
        return DB::transaction(function () use ($connection, $workspace, $plan, $trigger): ClockifySyncRun {
            $plannedJobs = $plan->jobs();

            $run = $this->runs->create([
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'workspace_id' => $workspace->id,
                'trigger' => $trigger->value,
                'mode' => $plan->mode->value,
                'priority' => $plan->priority->value,
                'status' => SyncRunStatus::PENDING->value,
                'range_start' => $plan->rangeStart,
                'range_end' => $plan->rangeEnd,
                'plan' => $plan->toArray(),
                'total_jobs' => count($plannedJobs),
                'correlation_id' => (string) Str::uuid(),
            ]);

            foreach ($plannedJobs as $job) {
                $this->jobs->create([
                    'sync_run_id' => $run->id,
                    'organization_id' => $connection->organization_id,
                    'workspace_id' => $workspace->id,
                    'entity_type' => $job->entityType->value,
                    'phase' => $job->phase->value,
                    'user_clockify_id' => $job->userId,
                    'range_start' => $job->rangeStart,
                    'range_end' => $job->rangeEnd,
                    'page_size' => $job->pageSize,
                    'status' => SyncJobStatus::PENDING->value,
                ]);
            }

            return $run;
        });
    }

    /**
     * Dispatch the next wave of pending jobs, bounded by concurrency and the
     * current API budget. When the budget is spent, a resume is scheduled for
     * the window reset instead of hammering Clockify.
     */
    public function dispatchPending(ClockifySyncRun $run): void
    {
        $run = $this->runs->findById($run->id) ?? $run;

        if ($run->status->isFinal()) {
            return;
        }

        $connection = $run->connection;
        $workspace = $run->workspace;

        if ($connection === null || $workspace === null) {
            return;
        }

        $wasStarted = $run->started_at !== null;

        $run = $this->runs->update($run, [
            'status' => SyncRunStatus::RUNNING->value,
            'started_at' => $run->started_at ?? now(),
        ]);

        if (! $wasStarted) {
            $this->events->runStarted($run);
        }

        // Starvation avoidance (SYNC-20): low-priority reconciliation only runs
        // when no higher-priority run is active and the window has headroom.
        if (! $this->mayDispatchLowPriority($run, $connection, $workspace)) {
            $this->scheduleResume($run);

            return;
        }

        $concurrency = $this->concurrency();
        $dispatched = 0;

        foreach ($this->jobs->dispatchableForRun($run) as $job) {
            if ($dispatched >= $concurrency) {
                break;
            }

            if (! $this->usage->canAfford($connection, $workspace)) {
                $this->events->budgetExhausted($run, $this->usage->snapshot($connection, $workspace)->resetsIn);
                $this->scheduleResume($run);

                return;
            }

            SyncEntityJob::dispatch($job->id, $run->priority->value);
            $dispatched++;
        }
    }

    /**
     * Whether a low-priority run may start now: no higher-priority run is
     * active and the current budget window has enough headroom (SYNC-20).
     */
    private function mayDispatchLowPriority(
        ClockifySyncRun $run,
        ClockifyConnection $connection,
        ClockifyWorkspace $workspace,
    ): bool {
        if ($run->priority !== SyncPriority::LOW) {
            return true;
        }

        if ($this->runs->hasActiveHigherPriority(SyncPriority::LOW)) {
            return false;
        }

        $snapshot = $this->usage->snapshot($connection, $workspace);
        $threshold = (float) config('clockify.sync_job.low_priority_min_free_ratio', 0.2);

        if ($snapshot->limit <= 0) {
            return false;
        }

        return ($snapshot->remaining / $snapshot->limit) >= $threshold;
    }

    /**
     * Recalculate the run from its jobs; finalize when every job is terminal,
     * otherwise dispatch the next wave.
     */
    public function onJobFinished(ClockifySyncJob $job): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $run = $this->runs->countsRecalculate($run);

        if ($run->total_jobs > 0 && $run->completed_jobs >= $run->total_jobs) {
            $this->finalize($run);

            return;
        }

        $this->dispatchPending($run);
    }

    /**
     * Resume an interrupted run (crash recovery): re-dispatch its non-final jobs.
     */
    public function resume(ClockifySyncRun $run): void
    {
        $this->dispatchPending($run);
    }

    public function cancel(ClockifySyncRun $run): ClockifySyncRun
    {
        $this->jobs->cancelNonFinal($run);

        $run = $this->runs->update($run, [
            'status' => SyncRunStatus::CANCELLED->value,
            'completed_at' => now(),
        ]);

        $this->audit->record(
            AuditEvent::SYNC_RUN_CANCELLED,
            $run,
            ['connection_id' => $run->connection_id],
            actor: auth()->user(),
            description: __('Sync run cancelled'),
        );

        $this->events->runCancelled($run);

        event(new SyncRunFinished($run));

        return $run;
    }

    private function finalize(ClockifySyncRun $run): void
    {
        $status = $this->finalStatus($run);

        $run = $this->runs->update($run, [
            'status' => $status->value,
            'completed_at' => now(),
        ]);

        $event = match ($status) {
            SyncRunStatus::FAILED => AuditEvent::SYNC_RUN_FAILED,
            SyncRunStatus::CANCELLED => AuditEvent::SYNC_RUN_CANCELLED,
            default => AuditEvent::SYNC_RUN_COMPLETED,
        };

        $this->audit->record(
            $event,
            $run,
            [
                'connection_id' => $run->connection_id,
                'jobs' => $run->total_jobs,
                'created' => $run->records_created,
                'updated' => $run->records_updated,
                'deleted' => $run->records_deleted,
            ],
            actor: auth()->user(),
            description: $event->label(),
        );

        match ($status) {
            SyncRunStatus::FAILED => $this->events->runFailed($run),
            SyncRunStatus::CANCELLED => $this->events->runCancelled($run),
            default => $this->events->runCompleted($run),
        };

        event(new SyncRunFinished($run));
    }

    private function finalStatus(ClockifySyncRun $run): SyncRunStatus
    {
        $completed = $this->jobs->countByStatus($run, SyncJobStatus::COMPLETED);
        $failed = $this->jobs->countByStatus($run, SyncJobStatus::FAILED);

        if ($completed === 0 && $failed > 0) {
            return SyncRunStatus::FAILED;
        }

        return SyncRunStatus::COMPLETED;
    }

    private function scheduleResume(ClockifySyncRun $run): void
    {
        $connection = $run->connection;
        $workspace = $run->workspace;

        if ($connection === null || $workspace === null) {
            return;
        }

        $snapshot = $this->usage->snapshot($connection, $workspace);

        ResumeSyncRunJob::dispatch($run->id, max(1, $snapshot->resetsIn));
    }

    private function concurrency(): int
    {
        return max(1, (int) config('clockify.sync_concurrency', 2));
    }
}
