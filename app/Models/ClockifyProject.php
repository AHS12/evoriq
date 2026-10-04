<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify project — the primary reporting dimension (ENT-04). Soft-deleted so
 * historical facts keep their project reference; `archived` is Clockify's own
 * flag and is independent of `deleted_at` (SYNC-07). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int|null $client_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $color
 * @property string|null $note
 * @property string|null $status
 * @property bool $archived
 * @property Carbon|null $archived_at
 * @property bool $billable
 * @property bool $public
 * @property string|null $billable_rate_amount
 * @property string|null $billable_rate_currency
 * @property string|null $cost_rate_amount
 * @property string|null $cost_rate_currency
 * @property string|null $estimated_hours
 * @property string|null $estimated_cost
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'client_id', 'clockify_id', 'name',
    'color', 'note', 'status', 'archived', 'archived_at', 'billable', 'public',
    'billable_rate_amount', 'billable_rate_currency', 'cost_rate_amount',
    'cost_rate_currency', 'estimated_hours', 'estimated_cost', 'raw_data',
    'synced_at', 'deleted_at',
])]
class ClockifyProject extends Model
{
    /**
     * @use HasFactory<ClockifyProjectFactory>
     * @use BelongsToOrganization<ClockifyProject>
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
            'billable' => 'boolean',
            'public' => 'boolean',
            'billable_rate_amount' => 'decimal:2',
            'cost_rate_amount' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
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
     * @return BelongsTo<ClockifyClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClockifyClient::class);
    }

    /**
     * @return HasMany<ClockifyProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ClockifyProjectMember::class, 'project_id');
    }
}
