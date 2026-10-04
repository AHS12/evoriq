<?php

namespace App\Repositories\Sync;

use App\Enums\SyncJobStatus;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use Illuminate\Support\Collection;

class ClockifySyncRunRepository implements ClockifySyncRunRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifySyncRun
    {
        return ClockifySyncRun::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifySyncRun
    {
        return ClockifySyncRun::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifySyncRun $run, array $data): ClockifySyncRun
    {
        $run->update($data);

        return $run->refresh();
    }

    public function delete(ClockifySyncRun $run): bool
    {
        return (bool) $run->delete();
    }

    /**
     * @return Collection<int, ClockifySyncRun>
     */
    public function forConnection(int $connectionId, int $limit = 20): Collection
    {
        return ClockifySyncRun::query()
            ->where('connection_id', $connectionId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function activeRun(): ?ClockifySyncRun
    {
        return ClockifySyncRun::query()
            ->whereNotIn('status', [
                SyncRunStatus::COMPLETED->value,
                SyncRunStatus::FAILED->value,
                SyncRunStatus::CANCELLED->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, ClockifySyncRun>
     */
    public function resumable(): Collection
    {
        return ClockifySyncRun::query()
            ->whereNotIn('status', [
                SyncRunStatus::COMPLETED->value,
                SyncRunStatus::FAILED->value,
                SyncRunStatus::CANCELLED->value,
            ])
            ->whereHas('jobs', function ($query): void {
                $query->where('status', SyncJobStatus::PENDING->value)
                    ->orWhere(function ($query): void {
                        $query->where('status', SyncJobStatus::RETRY_SCHEDULED->value)
                            ->where(function ($query): void {
                                $query->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
            })
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();
    }

    public function hasActiveHigherPriority(SyncPriority $priority): bool
    {
        $higher = $priority->higherLevels();

        if ($higher === []) {
            return false;
        }

        return ClockifySyncRun::query()
            ->whereNotIn('status', [
                SyncRunStatus::COMPLETED->value,
                SyncRunStatus::FAILED->value,
                SyncRunStatus::CANCELLED->value,
            ])
            ->whereIn('priority', array_map(static fn (SyncPriority $level): string => $level->value, $higher))
            ->exists();
    }

    public function countsRecalculate(ClockifySyncRun $run): ClockifySyncRun
    {
        $jobs = ClockifySyncJob::query()->where('sync_run_id', $run->id);

        $completed = (clone $jobs)
            ->whereIn('status', [
                SyncJobStatus::COMPLETED->value,
                SyncJobStatus::FAILED->value,
                SyncJobStatus::CANCELLED->value,
            ])
            ->count();

        $run->update([
            'total_jobs' => (clone $jobs)->count(),
            'completed_jobs' => $completed,
            'records_created' => (int) (clone $jobs)->sum('records_created'),
            'records_updated' => (int) (clone $jobs)->sum('records_updated'),
            'records_deleted' => (int) (clone $jobs)->sum('records_deleted'),
        ]);

        return $run->refresh();
    }
}
