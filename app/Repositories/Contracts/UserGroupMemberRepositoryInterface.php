<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyUserGroup;
use App\Models\ClockifyWorkspace;

interface UserGroupMemberRepositoryInterface
{
    /**
     * Replace a user group's member set (ENT-12). The incoming internal user ids
     * are authoritative, so members removed upstream disappear.
     *
     * @param  array<int, int>  $userIds  Internal user ids.
     * @return int Number of member rows written.
     */
    public function syncForGroup(ClockifyWorkspace $workspace, ClockifyUserGroup $group, array $userIds): int;

    /**
     * Remove every member row for a deleted group (ENT-13) so the team
     * dimension drops its members.
     */
    public function deleteForGroup(ClockifyWorkspace $workspace, string $groupClockifyId): int;
}
