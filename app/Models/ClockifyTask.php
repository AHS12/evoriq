<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify task — a nested per-project reporting dimension (ENT-05).
 * Soft-deleted so entries keep their task reference. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $project_id
 * @property int|null $assignee_user_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $status
 * @property bool $billable
 * @property string|null $estimated_hours
 * @property string|null $billable_rate_amount
 * @property string|null $billable_rate_currency
 * @property string|null $cost_rate_amount
 * @property string|null $cost_rate_currency
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'project_id', 'assignee_user_id',
    'clockify_id', 'name', 'status', 'billable', 'estimated_hours',
    'billable_rate_amount', 'billable_rate_currency', 'cost_rate_amount',
    'cost_rate_currency', 'completed_at', 'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyTask extends Model
{
    /**
     * @use HasFactory<ClockifyTaskFactory>
     * @use BelongsToOrganization<ClockifyTask>
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
            'billable' => 'boolean',
            'estimated_hours' => 'decimal:2',
            'billable_rate_amount' => 'decimal:2',
            'cost_rate_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClockifyProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClockifyProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<ClockifyUser, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ClockifyUser::class, 'assignee_user_id');
    }
}
