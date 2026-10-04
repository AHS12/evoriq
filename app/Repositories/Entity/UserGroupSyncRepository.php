<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyUser;
use App\Models\ClockifyUserGroup;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\UserGroupMemberRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Persists the user-group (team) dimension and its members (ENT-12). The handler
 * maps each group to attributes plus a (nullable) reserved `user_clockify_ids`
 * list; this repository resolves those ids in a batch, upserts the groups, then
 * replaces each group's member set. `null` leaves members untouched; an array
 * (possibly empty) is authoritative. `team_managers` is stored as raw Clockify
 * ids on the group.
 */
final class UserGroupSyncRepository extends AbstractSyncUpsertRepository
{
    public function __construct(
        protected UserGroupMemberRepositoryInterface $members,
    ) {}

    protected function syncModel(): string
    {
        return ClockifyUserGroup::class;
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
        $userClockifyIds = [];
        $prepared = [];

        foreach ($rows as $row) {
            $ids = $row['user_clockify_ids'] ?? null;
            unset($row['user_clockify_ids']);

            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if (is_string($id) && $id !== '') {
                        $userClockifyIds[$id] = true;
                    }
                }
            }

            $prepared[] = ['row' => $row, 'ids' => $ids];
        }

        $userIds = $this->resolveUserIds($workspace, array_keys($userClockifyIds));

        $counts = parent::upsertMany($workspace, array_map(
            static fn (array $item): array => $item['row'],
            $prepared,
        ));

        foreach ($prepared as $item) {
            if (! is_array($item['ids'])) {
                continue;
            }

            $group = $this->syncQuery()
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('clockify_id', (string) ($item['row']['clockify_id'] ?? ''))
                ->first();

            if (! $group instanceof ClockifyUserGroup) {
                continue;
            }

            $resolved = [];

            foreach ($item['ids'] as $id) {
                $internalId = is_string($id) ? ($userIds[$id] ?? null) : null;

                if ($internalId !== null) {
                    $resolved[] = $internalId;
                }
            }

            $this->members->syncForGroup($workspace, $group, $resolved);
        }

        return $counts;
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
