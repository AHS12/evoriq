<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyUserGroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The relational join between a user group (team) and a user (ENT-12). Replaced
 * per group on each sync, so a removed member disappears. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $user_group_id
 * @property int $user_id
 */
#[Fillable([
    'organization_id', 'workspace_id', 'user_group_id', 'user_id',
])]
class ClockifyUserGroupMember extends Model
{
    /**
     * @use HasFactory<ClockifyUserGroupMemberFactory>
     * @use BelongsToOrganization<ClockifyUserGroupMember>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * @return BelongsTo<ClockifyUserGroup, $this>
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(ClockifyUserGroup::class, 'user_group_id');
    }

    /**
     * @return BelongsTo<ClockifyUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ClockifyUser::class, 'user_id');
    }
}
