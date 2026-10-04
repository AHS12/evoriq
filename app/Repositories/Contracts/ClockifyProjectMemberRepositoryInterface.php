<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyProject;
use App\Models\ClockifyWorkspace;

interface ClockifyProjectMemberRepositoryInterface
{
    /**
     * Replace a project's member set (ENT-04). The incoming list is
     * authoritative, so members no longer present are removed. Rows are written
     * inside the caller's page transaction.
     *
     * @param  array<int, array<string, mixed>>  $members  Each with a resolved `user_id`.
     * @return int Number of member rows written.
     */
    public function syncForProject(
        ClockifyWorkspace $workspace,
        ClockifyProject $project,
        array $members,
    ): int;
}
