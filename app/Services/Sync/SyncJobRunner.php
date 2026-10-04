<?php

namespace App\Services\Sync;

use App\DTOs\Sync\UpsertCounts;
use App\Enums\SyncJobStatus;
use App\Exceptions\SyncBudgetExhausted;
use App\Models\ClockifySyncJob;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\Contracts\SyncHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes one sync job generically (SYNC-04): fetch a page, persist its raw
 * payloads, idempotently upsert the mapped entities inside a per-page
 * transaction, then advance the checkpoint. A job stopped after page N resumes
 * at page N+1, and a page failure never leaves partial normalized rows behind.
 *
 * The Clockify client reserves and records the API budget per request (SYNC-02),
 * so the runner does not spend budget itself.
 */
class SyncJobRunner
{
    public function __construct(
        protected SyncHandlerRegistry $handlers,
        protected RawRecordStore $rawRecords,
        protected ClockifySyncJobRepositoryInterface $jobs,
        protected ClockifySyncRunRepositoryInterface $runs,
        protected SyncEventEmitter $events,
        protected ClockifyClient $client,
    ) {}

    public function run(ClockifySyncJob $job): void
    {
        // A sync job never blocks on the API budget: on exhaustion it parks and
        // the run resumes at the window reset (SYNC-13/SYNC-20).
        $this->client->deferBudget(true);

        try {
            $handler = $this->handlers->resolve($job->entity_type);
            $context = $this->context($job);

            $job = $this->markRunning($job);

            // `page` holds the last completed page; resume from the next one.
            $page = $job->page + 1;

            while (true) {
                $job = $this->jobs->findById($job->id) ?? $job;

                if ($job->status === SyncJobStatus::CANCELLED) {
                    return;
                }

                $rawItems = $this->fetch($handler, $context, $page);

                if ($rawItems === []) {
                    $this->complete($job);

                    return;
                }

                $this->rawRecords->putMany($context->workspace, $job->entity_type, $rawItems);

                $counts = $this->upsertPage($handler, $context, $rawItems);

                $job = $this->jobs->advancePage($job, $page, [
                    'processed' => count($rawItems),
                    'created' => $counts->created,
                    'updated' => $counts->updated,
                ]);

                $this->events->jobProgress($job);

                if (count($rawItems) < $job->page_size) {
                    $this->complete($job);

                    return;
                }

                $page++;
            }
        } catch (SyncBudgetExhausted $exception) {
            $this->parkForBudget($job, $exception->resetsIn);
        } finally {
            $this->client->deferBudget(false);
        }
    }

    /**
     * Park a job at the budget edge without burning an attempt; the run's
     * pending dispatch/resume picks it up at the next reset.
     */
    private function parkForBudget(ClockifySyncJob $job, int $resetsIn): void
    {
        $this->jobs->update($job, [
            'status' => SyncJobStatus::PENDING->value,
            'attempt' => max(0, $job->attempt - 1),
            'next_retry_at' => now()->addSeconds(max(1, $resetsIn)),
            'heartbeat_at' => now(),
        ]);

        $run = $job->syncRun;

        if ($run !== null) {
            $this->events->budgetExhausted($run, $resetsIn);
        }
    }

    private function markRunning(ClockifySyncJob $job): ClockifySyncJob
    {
        $job = $this->jobs->update($job, [
            'status' => SyncJobStatus::RUNNING->value,
            'attempt' => $job->attempt + 1,
            'started_at' => $job->started_at ?? now(),
            'heartbeat_at' => now(),
            'last_error' => null,
        ]);

        $this->events->jobStarted($job);

        return $job;
    }

    private function complete(ClockifySyncJob $job): void
    {
        $job = $this->jobs->update($job, [
            'status' => SyncJobStatus::COMPLETED->value,
            'completed_at' => now(),
            'heartbeat_at' => now(),
        ]);

        $this->events->jobCompleted($job);

        $run = $job->syncRun;

        if ($run !== null) {
            $this->runs->countsRecalculate($run);
        }
    }

    /**
     * Map and upsert one page inside a single transaction.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     */
    private function upsertPage(SyncHandler $handler, SyncContext $context, array $rawItems): UpsertCounts
    {
        return DB::transaction(function () use ($handler, $context, $rawItems): UpsertCounts {
            $repository = $handler->repository();

            $mapped = [];

            foreach ($rawItems as $raw) {
                try {
                    $mapped[] = $handler->map($raw, $context);
                } catch (Throwable $exception) {
                    // A single malformed row must never fail the whole page
                    // (ENT-00); record a warning and skip it.
                    Log::warning('Skipping a malformed sync row.', [
                        'entity' => $handler->entityType()->value,
                        'workspace_id' => $context->workspace->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            return $repository->upsertMany($context->workspace, $mapped);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(SyncHandler $handler, SyncContext $context, int $page): array
    {
        $items = $handler->fetchPage($context, $page);

        if (is_array($items)) {
            return $items;
        }

        return iterator_to_array($items, false);
    }

    private function context(ClockifySyncJob $job): SyncContext
    {
        $run = $job->syncRun;
        $workspace = $job->workspace ?? $run?->workspace;

        if ($run === null || $workspace === null) {
            throw new RuntimeException('The sync job is missing its run or workspace.');
        }

        return new SyncContext(
            connection: $run->connection,
            workspace: $workspace,
            rangeStart: $job->range_start,
            rangeEnd: $job->range_end,
            pageSize: $job->page_size,
            userId: $job->user_clockify_id,
        );
    }
}
