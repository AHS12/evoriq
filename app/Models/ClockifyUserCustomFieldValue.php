<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyUserCustomFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A custom-field value on a Clockify user (ENT-11) — team/department/location
 * style metadata dimensions. Keyed by
 * `(organization_id, workspace_id, user_id, custom_field_id)`; the value is JSON.
 * Cascades with the user and field.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $user_id
 * @property int $custom_field_id
 * @property mixed $value
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'user_id', 'custom_field_id',
    'value', 'raw_data',
])]
class ClockifyUserCustomFieldValue extends Model
{
    /**
     * @use HasFactory<ClockifyUserCustomFieldValueFactory>
     * @use BelongsToOrganization<ClockifyUserCustomFieldValue>
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
            'value' => 'array',
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
     * @return BelongsTo<ClockifyCustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(ClockifyCustomField::class, 'custom_field_id');
    }
}
