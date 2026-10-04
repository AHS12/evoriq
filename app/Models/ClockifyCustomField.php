<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyCustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify custom-field definition (ENT-09) — the metadata dimension whose
 * values describe time entries and users (ENT-10/11). Definitions are
 * soft-deleted so historical values still resolve. `type`/`entity_type`/`status`
 * are stored as strings because Clockify's enum set is only partially
 * documented; unknown values are tolerated. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $entity_type
 * @property string|null $status
 * @property bool $required
 * @property bool $only_admin_can_edit
 * @property array<int, mixed>|null $allowed_values
 * @property string|null $placeholder
 * @property mixed $workspace_default_value
 * @property mixed $project_default_values
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'name', 'description',
    'type', 'entity_type', 'status', 'required', 'only_admin_can_edit',
    'allowed_values', 'placeholder', 'workspace_default_value',
    'project_default_values', 'raw_data', 'synced_at', 'deleted_at',
])]
class ClockifyCustomField extends Model
{
    /**
     * @use HasFactory<ClockifyCustomFieldFactory>
     * @use BelongsToOrganization<ClockifyCustomField>
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
            'required' => 'boolean',
            'only_admin_can_edit' => 'boolean',
            'allowed_values' => 'array',
            'workspace_default_value' => 'array',
            'project_default_values' => 'array',
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
}
