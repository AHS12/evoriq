<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's relationship to a workspace, project or user group, with the rates
 * that applied (ENT-02/04/12). Rows are keyed by target + `effective_from`, so a
 * rate change appends history instead of overwriting it. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $user_id
 * @property string $membership_type
 * @property string|null $membership_status
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $hourly_rate_amount
 * @property string|null $hourly_rate_currency
 * @property string|null $cost_rate_amount
 * @property string|null $cost_rate_currency
 * @property Carbon|null $effective_from
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'user_id', 'membership_type',
    'membership_status', 'target_type', 'target_id', 'hourly_rate_amount',
    'hourly_rate_currency', 'cost_rate_amount', 'cost_rate_currency',
    'effective_from', 'raw_data',
])]
class ClockifyMembership extends Model
{
    /**
     * @use HasFactory<ClockifyMembershipFactory>
     * @use BelongsToOrganization<ClockifyMembership>
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
            'hourly_rate_amount' => 'decimal:2',
            'cost_rate_amount' => 'decimal:2',
            'effective_from' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ClockifyUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ClockifyUser::class, 'user_id');
    }

    /**
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }
}
