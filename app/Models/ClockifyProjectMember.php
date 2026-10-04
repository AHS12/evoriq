<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyProjectMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's membership of a project and their project-specific rates (ENT-04).
 * Replaced per project on each sync (the member set is authoritative), so a
 * removed member disappears. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $project_id
 * @property int $user_id
 * @property string $membership_type
 * @property string|null $membership_status
 * @property string|null $hourly_rate_amount
 * @property string|null $hourly_rate_currency
 * @property string|null $cost_rate_amount
 * @property string|null $cost_rate_currency
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'project_id', 'user_id',
    'membership_type', 'membership_status', 'hourly_rate_amount',
    'hourly_rate_currency', 'cost_rate_amount', 'cost_rate_currency', 'raw_data',
])]
class ClockifyProjectMember extends Model
{
    /**
     * @use HasFactory<ClockifyProjectMemberFactory>
     * @use BelongsToOrganization<ClockifyProjectMember>
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
            'raw_data' => 'array',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(ClockifyUser::class, 'user_id');
    }
}
