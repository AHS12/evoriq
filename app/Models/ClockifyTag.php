<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify tag dimension (ENT-06). Tags are related to time entries through
 * {@see ClockifyTimeEntryTag} (a relational join, never JSON). Soft-deleted so
 * historical joins keep their tag. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string $name
 * @property bool $archived
 * @property Carbon|null $archived_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'name', 'archived',
    'archived_at', 'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyTag extends Model
{
    /**
     * @use HasFactory<ClockifyTagFactory>
     * @use BelongsToOrganization<ClockifyTag>
     */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    /**
     * @return BelongsToMany<ClockifyTimeEntry, $this>
     */
    public function timeEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            ClockifyTimeEntry::class,
            'clockify_time_entry_tags',
            'tag_id',
            'time_entry_id',
        );
    }
}
