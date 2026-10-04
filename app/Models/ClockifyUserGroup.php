<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyUserGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify user group (team) dimension (ENT-12). `team_managers` holds the
 * Clockify ids of the group's managers; membership is relational through
 * {@see ClockifyUserGroupMember}. Soft-deleted so historical facts keep their
 * team. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $status
 * @property array<int, string>|null $team_managers
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'name', 'status',
    'team_managers', 'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyUserGroup extends Model
{
    /**
     * @use HasFactory<ClockifyUserGroupFactory>
     * @use BelongsToOrganization<ClockifyUserGroup>
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
            'team_managers' => 'array',
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
     * @return BelongsToMany<ClockifyUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            ClockifyUser::class,
            'clockify_user_group_members',
            'user_group_id',
            'user_id',
        );
    }
}
