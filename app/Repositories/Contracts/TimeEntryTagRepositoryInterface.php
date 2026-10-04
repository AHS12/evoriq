<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyWorkspace;

interface TimeEntryTagRepositoryInterface
{
    /**
     * Replace a time entry's tag joins (ENT-06/ENT-07). The incoming tag id set
     * is authoritative, so tags removed upstream disappear. Unknown tag ids
     * (never synced) are skipped rather than failing the entry.
     *
     * @param  array<int, string>  $tagClockifyIds
     * @return int Number of joins written.
     */
    public function syncForTimeEntry(
        ClockifyWorkspace $workspace,
        ClockifyTimeEntry $entry,
        array $tagClockifyIds,
    ): int;

    /**
     * Remove every join row for a deleted tag (ENT-13) so the tag disappears
     * from entries.
     */
    public function deleteForTag(ClockifyWorkspace $workspace, string $tagClockifyId): int;
}
