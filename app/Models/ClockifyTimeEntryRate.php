<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTimeEntryRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The rate that historically applied to one time entry (ENT-08). Rates are
 * facts: analytics must use the rate recorded here, never today's project rate.
 * Keyed by `(organization_id, workspace_id, time_entry_id)`; many entries have
 * no explicit rate (nulls), so downstream falls back to project/membership
 * rates on demand (ANA-08). Organization-owned; cascades with the entry.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string|null $clockify_id
 * @property int $time_entry_id
 * @property int|null $user_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property string|null $billable_rate_amount
 * @property string|null $billable_rate_currency
 * @property string|null $cost_rate_amount
 * @property string|null $cost_rate_currency
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'time_entry_id',
    'user_id', 'project_id', 'task_id', 'billable_rate_amount',
    'billable_rate_currency', 'cost_rate_amount', 'cost_rate_currency',
    'raw_data',
])]
class ClockifyTimeEntryRate extends Model
{
    /**
     * @use HasFactory<ClockifyTimeEntryRateFactory>
     * @use BelongsToOrganization<ClockifyTimeEntryRate>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billable_rate_amount' => 'decimal:2',
            'cost_rate_amount' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ClockifyTimeEntry, $this>
     */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(ClockifyTimeEntry::class, 'time_entry_id');
    }
}
