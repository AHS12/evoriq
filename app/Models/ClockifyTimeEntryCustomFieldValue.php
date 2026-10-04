<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyTimeEntryCustomFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A custom-field value on a time entry (ENT-10) — a historical metadata fact,
 * kept in its own table (not JSON on the entry) because the Entity Changes API
 * treats it as a separate entity. Keyed by
 * `(organization_id, workspace_id, time_entry_id, custom_field_id)`; the value
 * is JSON (text/number/bool/array depending on the field type). Cascades with
 * the entry and field.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property int $time_entry_id
 * @property int $custom_field_id
 * @property mixed $value
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'time_entry_id', 'custom_field_id',
    'value', 'raw_data',
])]
class ClockifyTimeEntryCustomFieldValue extends Model
{
    /**
     * @use HasFactory<ClockifyTimeEntryCustomFieldValueFactory>
     * @use BelongsToOrganization<ClockifyTimeEntryCustomFieldValue>
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
     * @return BelongsTo<ClockifyTimeEntry, $this>
     */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(ClockifyTimeEntry::class, 'time_entry_id');
    }

    /**
     * @return BelongsTo<ClockifyCustomField, $this>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(ClockifyCustomField::class, 'custom_field_id');
    }
}
