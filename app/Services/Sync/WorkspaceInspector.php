<?php

namespace App\Services\Sync;

use App\DTOs\Sync\WorkspaceInspection;
use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Clockify\ClockifyClient;
use Carbon\CarbonInterface;

/**
 * A lightweight plan-time probe of a workspace (SYNC-03): it resolves the users
 * a fact fan-out must iterate. Clockify exposes no total counts, so volume
 * hints are optional and may be added per entity as a count strategy lands
 * (SPIKE-06); the planner tolerates their absence.
 */
class WorkspaceInspector
{
    public function __construct(
        protected ClockifyClient $client,
    ) {}

    /**
     * @param  array<int, SyncEntityType>  $entities
     */
    public function inspect(
        ClockifyConnection $connection,
        ClockifyWorkspace $workspace,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        array $entities,
    ): WorkspaceInspection {
        $users = $this->needsUsers($entities) ? $this->users($connection, $workspace) : [];

        return new WorkspaceInspection(users: $users);
    }

    /**
     * @param  array<int, SyncEntityType>  $entities
     */
    private function needsUsers(array $entities): bool
    {
        foreach ([
            SyncEntityType::USER,
            SyncEntityType::TIME_ENTRY,
            SyncEntityType::TIME_ENTRY_RATE,
            SyncEntityType::TIME_ENTRY_CUSTOM_FIELD_VALUE,
        ] as $entity) {
            if (in_array($entity, $entities, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every workspace member, in the order Clockify returns them.
     *
     * @return array<int, array{id: string, name: string|null}>
     */
    private function users(ClockifyConnection $connection, ClockifyWorkspace $workspace): array
    {
        // Inspect is a web-request path: never block on the API budget. If the
        // window is exhausted the call throws (SyncBudgetExhausted) and the
        // caller shows "start without estimate" instead of hanging the server.
        $client = $this->client->forConnection($connection, $workspace)->deferBudget();

        $users = [];

        foreach ($client->paginate("/workspaces/{$workspace->clockify_id}/users", ['page-size' => 200]) as $page) {
            foreach ($page as $user) {
                if (! is_array($user) || ! isset($user['id'])) {
                    continue;
                }

                $users[] = [
                    'id' => (string) $user['id'],
                    'name' => isset($user['name']) ? (string) $user['name'] : null,
                ];
            }
        }

        return $users;
    }
}
