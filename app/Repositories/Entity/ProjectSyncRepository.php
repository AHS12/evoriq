<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyClient;
use App\Models\ClockifyProject;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyProjectMemberRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Persists the project dimension and its derived project members (ENT-04). The
 * handler maps each project to attributes plus a reserved `client_clockify_id`
 * and a (nullable) `memberships` list; this repository resolves Clockify ids to
 * internal ids in a batch, upserts the projects, then replaces each project's
 * member set.
 */
final class ProjectSyncRepository extends AbstractSyncUpsertRepository
{
    public function __construct(
        protected ClockifyProjectMemberRepositoryInterface $members,
    ) {}

    protected function syncModel(): string
    {
        return ClockifyProject::class;
    }

    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        $this->persist($workspace, [$attributes]);

        return $this->syncQuery()
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', (string) ($attributes['clockify_id'] ?? ''))
            ->firstOrFail();
    }

    public function upsertMany(ClockifyWorkspace $workspace, iterable $rows): UpsertCounts
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $row;
        }

        return $this->persist($workspace, $normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persist(ClockifyWorkspace $workspace, array $rows): UpsertCounts
    {
        $prepared = [];
        $clientClockifyIds = [];
        $userClockifyIds = [];

        foreach ($rows as $row) {
            $clientClockifyId = $row['client_clockify_id'] ?? null;
            $memberships = array_key_exists('memberships', $row) ? $row['memberships'] : null;
            unset($row['client_clockify_id'], $row['memberships']);

            if (is_string($clientClockifyId) && $clientClockifyId !== '') {
                $row['client_clockify_id'] = $clientClockifyId;
                $clientClockifyIds[$clientClockifyId] = true;
            }

            if (is_array($memberships)) {
                foreach ($memberships as $membership) {
                    $userId = $membership['user_clockify_id'] ?? null;

                    if (is_string($userId) && $userId !== '') {
                        $userClockifyIds[$userId] = true;
                    }
                }
            }

            $prepared[] = ['row' => $row, 'memberships' => $memberships];
        }

        $clientIds = $this->resolveClientIds($workspace, array_keys($clientClockifyIds));
        $userIds = $this->resolveUserIds($workspace, array_keys($userClockifyIds));

        $projectRows = [];

        foreach ($prepared as $item) {
            $row = $item['row'];
            $clientClockifyId = $row['client_clockify_id'] ?? null;
            unset($row['client_clockify_id']);

            $row['client_id'] = $clientClockifyId !== null
                ? ($clientIds[$clientClockifyId] ?? null)
                : null;

            $projectRows[] = $row;
        }

        $counts = parent::upsertMany($workspace, $projectRows);

        foreach ($prepared as $item) {
            if ($item['memberships'] === null) {
                continue;
            }

            $project = $this->syncQuery()
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('clockify_id', (string) ($item['row']['clockify_id'] ?? ''))
                ->first();

            if (! $project instanceof ClockifyProject) {
                continue;
            }

            $resolved = [];

            foreach ($item['memberships'] as $membership) {
                $internalId = $userIds[(string) ($membership['user_clockify_id'] ?? '')] ?? null;

                if ($internalId === null) {
                    continue;
                }

                unset($membership['user_clockify_id']);
                $membership['user_id'] = $internalId;
                $resolved[] = $membership;
            }

            $this->members->syncForProject($workspace, $project, $resolved);
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveClientIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $clients = ClockifyClient::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($clients as $client) {
            $map[(string) $client->getAttribute('clockify_id')] = (int) $client->getKey();
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveUserIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $users = ClockifyUser::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($users as $user) {
            $map[(string) $user->getAttribute('clockify_id')] = (int) $user->getKey();
        }

        return $map;
    }
}
