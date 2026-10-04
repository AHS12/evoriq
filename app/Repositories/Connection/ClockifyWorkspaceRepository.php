<?php

namespace App\Repositories\Connection;

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClockifyWorkspaceRepository implements ClockifyWorkspaceRepositoryInterface
{
    public function upsertMany(ClockifyConnection $connection, array $workspaces): Collection
    {
        $organizationId = $connection->organization_id;

        $results = new Collection;

        foreach ($workspaces as $workspace) {
            $results->push(
                ClockifyWorkspace::query()
                    ->withoutOrganizationScope()
                    ->updateOrCreate(
                        [
                            'organization_id' => $organizationId,
                            'clockify_id' => $workspace['clockify_id'],
                        ],
                        [
                            ...$workspace,
                            'organization_id' => $organizationId,
                            'connection_id' => $connection->id,
                        ],
                    ),
            );
        }

        return $results;
    }

    public function forConnection(ClockifyConnection $connection): Collection
    {
        return ClockifyWorkspace::query()
            ->where('connection_id', $connection->id)
            ->get();
    }

    public function findByClockifyId(int $connectionId, string $clockifyId): ?ClockifyWorkspace
    {
        return ClockifyWorkspace::query()
            ->where('connection_id', $connectionId)
            ->where('clockify_id', $clockifyId)
            ->first();
    }

    public function markActive(ClockifyConnection $connection, string $clockifyId): void
    {
        ClockifyWorkspace::query()
            ->where('connection_id', $connection->id)
            ->update(['active' => false]);

        ClockifyWorkspace::query()
            ->where('connection_id', $connection->id)
            ->where('clockify_id', $clockifyId)
            ->update(['active' => true]);
    }

    public function markInactive(ClockifyConnection $connection, string $clockifyId): void
    {
        ClockifyWorkspace::query()
            ->where('connection_id', $connection->id)
            ->where('clockify_id', $clockifyId)
            ->update(['active' => false]);
    }

    public function advanceChangeFeedCursor(ClockifyWorkspace $workspace, CarbonInterface $at): ClockifyWorkspace
    {
        $workspace->update(['change_feed_cursor_at' => $at]);

        return $workspace->refresh();
    }
}
