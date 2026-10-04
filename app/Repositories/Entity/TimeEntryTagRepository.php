<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyTag;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryTag;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\TimeEntryTagRepositoryInterface;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * All data access for the time-entry ↔ tag join (ENT-06). The set is
 * authoritative per entry, so each sync deletes the entry's joins and inserts
 * the resolved set — adds and removals both reflect, and re-runs never
 * duplicate. Unknown tag ids are skipped.
 */
class TimeEntryTagRepository implements TimeEntryTagRepositoryInterface
{
    public function deleteForTag(ClockifyWorkspace $workspace, string $tagClockifyId): int
    {
        $tag = ClockifyTag::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', $tagClockifyId)
            ->first(['id']);

        if ($tag === null) {
            return 0;
        }

        return ClockifyTimeEntryTag::query()
            ->withoutOrganizationScope()
            ->where('tag_id', $tag->getKey())
            ->delete();
    }

    public function syncForTimeEntry(
        ClockifyWorkspace $workspace,
        ClockifyTimeEntry $entry,
        array $tagClockifyIds,
    ): int {
        ClockifyTimeEntryTag::query()
            ->withoutOrganizationScope()
            ->where('time_entry_id', $entry->id)
            ->delete();

        if ($tagClockifyIds === []) {
            return 0;
        }

        $tags = ClockifyTag::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $tagClockifyIds)
            ->get(['id', 'clockify_id']);

        $written = 0;

        foreach ($tags as $tag) {
            ClockifyTimeEntryTag::query()
                ->withoutOrganizationScope()
                ->updateOrCreate(
                    [
                        'time_entry_id' => $entry->id,
                        'tag_id' => (int) $tag->getKey(),
                    ],
                    [
                        'organization_id' => $workspace->organization_id,
                        'workspace_id' => $workspace->id,
                    ],
                );

            $written++;
        }

        return $written;
    }
}
