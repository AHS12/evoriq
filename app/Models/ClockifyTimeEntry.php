<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify time entry — the central analytical fact (ENT-07). Soft-deleted so
 * an upstream deletion (SYNC-07) keeps history; `duration_seconds` is derived
 * from the interval (running entries have no end). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property string $clockify_id
 * @property string|null $description
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property int|null $duration_seconds
 * @property bool $billable
 * @property string|null $type
 * @property string|null $time_zone
 * @property bool $is_locked
 * @property bool $is_in_progress
 * @property string|null $approval_status
 * @property string|null $cost_amount
 * @property string|null $cost_currency
 * @property string|null $billable_amount
 * @property string|null $billable_currency
 * @property Carbon|null $clockify_created_at
 * @property Carbon|null $clockify_updated_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'user_id', 'project_id', 'task_id',
    'clockify_id', 'description', 'start_at', 'end_at', 'duration_seconds',
    'billable', 'type', 'time_zone', 'is_locked', 'is_in_progress',
    'approval_status', 'cost_amount', 'cost_currency', 'billable_amount',
    'billable_currency', 'clockify_created_at', 'clockify_updated_at',
    'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyTimeEntry extends Model
{
    /**
     * @use HasFactory<ClockifyTimeEntryFactory>
     * @use BelongsToOrganization<ClockifyTimeEntry>
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
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_seconds' => 'integer',
            'billable' => 'boolean',
            'is_locked' => 'boolean',
            'is_in_progress' => 'boolean',
            'cost_amount' => 'decimal:2',
            'billable_amount' => 'decimal:2',
            'clockify_created_at' => 'datetime',
            'clockify_updated_at' => 'datetime',
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
     * @return BelongsTo<ClockifyUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ClockifyUser::class, 'user_id');
    }

    /**
     * @return BelongsTo<ClockifyProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClockifyProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<ClockifyTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ClockifyTask::class, 'task_id');
    }

    /**
     * @return BelongsToMany<ClockifyTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ClockifyTag::class,
            'clockify_time_entry_tags',
            'time_entry_id',
            'tag_id',
        );
    }
}
