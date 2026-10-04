<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;

interface ClockifyMembershipRepositoryInterface
{
    /**
     * Persist a user's memberships and rates (ENT-02). Rows are keyed by
     * target + `effective_from`, so a rate that starts at a new `since`
     * appends a new row instead of overwriting the historical one.
     *
     * @param  array<int, array<string, mixed>>  $memberships
     * @return int Number of membership rows written.
     */
    public function syncForUser(
        ClockifyWorkspace $workspace,
        ClockifyUser $user,
        array $memberships,
    ): int;

    /**
     * Remove every membership row for a deleted user (ENT-13): an ended
     * relationship drops the workspace rows (historical rates live on entry
     * rates, not memberships).
     */
    public function deleteForUser(ClockifyWorkspace $workspace, string $userClockifyId): int;
}
