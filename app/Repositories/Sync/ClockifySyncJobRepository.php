<?php

namespace App\Repositories\Sync;

use App\Enums\SyncJobStatus;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClockifySyncJobRepository implements ClockifySyncJobRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifySyncJob
    {
        return ClockifySyncJob::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifySyncJob
    {
        return ClockifySyncJob::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifySyncJob $job, array $data): ClockifySyncJob
    {
        $job->update($data);

        return $job->refresh();
    }

    public function delete(ClockifySyncJob $job): bool
    {
        return (bool) $job->delete();
    }

    /**
     * @return Collection<int, ClockifySyncJob>
     */
    public function forRun(ClockifySyncRun|int $run): Collection
    {
        $runId = $run instanceof ClockifySyncRun ? $run->id : $run;

        return ClockifySyncJob::query()
            ->where('sync_run_id', $runId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkpoint(ClockifySyncJob $job): array
    {
        return $job->checkpoint ?? [];
    }

    /**
     * @param  array{processed?: int, created?: int, updated?: int, deleted?: int}  $counts
     */
    public function advancePage(ClockifySyncJob $job, int $page, array $counts = []): ClockifySyncJob
    {
        $job->update([
            'page' => $page,
            'heartbeat_at' => now(),
            'records_processed' => $job->records_processed + (int) ($counts['processed'] ?? 0),
            'records_created' => $job->records_created + (int) ($counts['created'] ?? 0),
            'records_updated' => $job->records_updated + (int) ($counts['updated'] ?? 0),
            'records_deleted' => $job->records_deleted + (int) ($counts['deleted'] ?? 0),
        ]);

        return $job->refresh();
    }

    /**
     * @return Collection<int, ClockifySyncJob>
     */
    public function pendingForRun(ClockifySyncRun|int $run): Collection
    {
        return ClockifySyncJob::query()
            ->where('sync_run_id', $run instanceof ClockifySyncRun ? $run->id : $run)
            ->where('status', SyncJobStatus::PENDING->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ClockifySyncJob>
     */
    public function dispatchableForRun(ClockifySyncRun|int $run): Collection
    {
        $runId = $run instanceof ClockifySyncRun ? $run->id : $run;

        return ClockifySyncJob::query()
            ->where('sync_run_id', $runId)
            ->where(function ($query): void {
                $query->where('status', SyncJobStatus::PENDING->value)
                    ->orWhere(function ($query): void {
                        $query->where('status', SyncJobStatus::RETRY_SCHEDULED->value)
                            ->where(function ($query): void {
                                $query->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ClockifySyncJob>
     */
    public function staleRunning(CarbonInterface $before, int $limit = 100): Collection
    {
        return ClockifySyncJob::query()
            ->where('status', SyncJobStatus::RUNNING->value)
            ->where(function ($query) use ($before): void {
                $query->where('heartbeat_at', '<', $before)
                    ->orWhere(function ($query) use ($before): void {
                        $query->whereNull('heartbeat_at')
                            ->where('started_at', '<', $before);
                    });
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    public function cancelNonFinal(ClockifySyncRun|int $run): int
    {
        return ClockifySyncJob::query()
            ->where('sync_run_id', $run instanceof ClockifySyncRun ? $run->id : $run)
            ->whereNotIn('status', [
                SyncJobStatus::COMPLETED->value,
                SyncJobStatus::FAILED->value,
                SyncJobStatus::CANCELLED->value,
            ])
            ->update([
                'status' => SyncJobStatus::CANCELLED->value,
                'completed_at' => now(),
            ]);
    }

    public function countByStatus(ClockifySyncRun|int $run, SyncJobStatus $status): int
    {
        return ClockifySyncJob::query()
            ->where('sync_run_id', $run instanceof ClockifySyncRun ? $run->id : $run)
            ->where('status', $status->value)
            ->count();
    }
}
