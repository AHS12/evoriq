<?php

namespace App\Repositories\Sync;

use App\Enums\ApiUsageWindowType;
use App\Models\ClockifyApiUsage;
use App\Repositories\Contracts\ClockifyApiUsageRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

class ClockifyApiUsageRepository implements ClockifyApiUsageRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyApiUsage
    {
        return ClockifyApiUsage::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyApiUsage
    {
        return ClockifyApiUsage::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyApiUsage $usage, array $data): ClockifyApiUsage
    {
        $usage->update($data);

        return $usage->refresh();
    }

    public function delete(ClockifyApiUsage $usage): bool
    {
        return (bool) $usage->delete();
    }

    public function findWindow(
        int $connectionId,
        ?int $workspaceId,
        ApiUsageWindowType $windowType,
        CarbonInterface $windowStartedAt,
    ): ?ClockifyApiUsage {
        return ClockifyApiUsage::query()
            ->where('connection_id', $connectionId)
            ->where('workspace_id', $workspaceId)
            ->where('window_type', $windowType->value)
            ->where('window_started_at', $windowStartedAt)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreateWindow(array $attributes, array $values): ClockifyApiUsage
    {
        $query = ClockifyApiUsage::query()->withoutOrganizationScope();

        $existing = (clone $query)->where($attributes)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $query->create([...$attributes, ...$values]);
        } catch (QueryException) {
            // Another worker won the race; re-read the window it created.
            return ClockifyApiUsage::query()
                ->withoutOrganizationScope()
                ->where($attributes)
                ->firstOrFail();
        }
    }

    public function lockWindow(int $id): ?ClockifyApiUsage
    {
        return ClockifyApiUsage::query()
            ->withoutOrganizationScope()
            ->lockForUpdate()
            ->find($id);
    }

    public function incrementRequests(ClockifyApiUsage $usage, int $amount = 1): ClockifyApiUsage
    {
        $usage->increment('requests_used', $amount);

        return $usage->refresh();
    }
}
