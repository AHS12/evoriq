<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyWorkspace;

interface ClockifyProjectRepositoryInterface
{
    /**
     * The Clockify ids of every project in a workspace, ordered for stable
     * nested iteration (ENT-05 fans tasks out per project).
     *
     * @return array<int, string>
     */
    public function listClockifyIds(ClockifyWorkspace $workspace): array;
}
