<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTimeEntryTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The relational join between a time entry and a tag (ENT-06). Kept as rows —
 * never a JSON array on the entry — so hours-by-tag stays queryable. Written by
 * the time-entry sync (delete–insert per entry).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $time_entry_id
 * @property int $tag_id
 */
#[Fillable([
    'organization_id', 'workspace_id', 'time_entry_id', 'tag_id',
])]
class ClockifyTimeEntryTag extends Model
{
    /**
     * @use HasFactory<ClockifyTimeEntryTagFactory>
     * @use BelongsToOrganization<ClockifyTimeEntryTag>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * @return BelongsTo<ClockifyTimeEntry, $this>
     */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(ClockifyTimeEntry::class, 'time_entry_id');
    }

    /**
     * @return BelongsTo<ClockifyTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(ClockifyTag::class, 'tag_id');
    }
}
