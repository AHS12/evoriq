<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyUserGroup;
use App\Models\ClockifyUserGroupMember;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\UserGroupMemberRepositoryInterface;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * All data access for user-group membership (ENT-12). A group's member set is
 * authoritative, so each sync deletes the group's rows and inserts the incoming
 * set — removals reflect and re-runs never duplicate.
 */
class UserGroupMemberRepository implements UserGroupMemberRepositoryInterface
{
    public function deleteForGroup(ClockifyWorkspace $workspace, string $groupClockifyId): int
    {
        $group = ClockifyUserGroup::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', $groupClockifyId)
            ->first(['id']);

        if ($group === null) {
            return 0;
        }

        return ClockifyUserGroupMember::query()
            ->withoutOrganizationScope()
            ->where('user_group_id', $group->getKey())
            ->delete();
    }

    public function syncForGroup(ClockifyWorkspace $workspace, ClockifyUserGroup $group, array $userIds): int
    {
        ClockifyUserGroupMember::query()
            ->withoutOrganizationScope()
            ->where('user_group_id', $group->id)
            ->delete();

        $written = 0;

        foreach (array_unique($userIds) as $userId) {
            ClockifyUserGroupMember::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'user_group_id' => $group->id,
                    'user_id' => $userId,
                ]);

            $written++;
        }

        return $written;
    }
}
