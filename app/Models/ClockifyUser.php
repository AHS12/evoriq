<?php

namespace App\Models;

use App\Enums\ClockifyUserStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify user (person) dimension (ENT-02). Identity only; rates and
 * relationships live on {@see ClockifyMembership} so history is preserved.
 * Organization-owned and soft-deleted so a departed user keeps their facts.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $email
 * @property ClockifyUserStatus|null $status
 * @property string|null $profile_picture_url
 * @property string|null $timezone
 * @property string|null $week_start
 * @property array<string, mixed>|null $working_days
 * @property int|null $work_capacity
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'name', 'email', 'status',
    'profile_picture_url', 'timezone', 'week_start', 'working_days',
    'work_capacity', 'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyUser extends Model
{
    /**
     * @use HasFactory<ClockifyUserFactory>
     * @use BelongsToOrganization<ClockifyUser>
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
            'status' => ClockifyUserStatus::class,
            'working_days' => 'array',
            'work_capacity' => 'integer',
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * The workspace the user belongs to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    /**
     * The user's workspace/project/user-group memberships and rates.
     *
     * @return HasMany<ClockifyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClockifyMembership::class, 'user_id');
    }
}
