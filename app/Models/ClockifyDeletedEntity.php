<?php

namespace App\Models;

use App\Enums\SyncEntityType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyDeletedEntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A deletion reported by Clockify, recorded once and applied idempotently
 * (SYNC-01, SYNC-07). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property SyncEntityType $entity_type
 * @property string $clockify_id
 * @property Carbon $deleted_at
 * @property string|null $document_code
 * @property Carbon|null $applied_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'entity_type', 'clockify_id',
    'deleted_at', 'document_code', 'applied_at', 'raw_data',
])]
class ClockifyDeletedEntity extends Model
{
    /**
     * @use HasFactory<ClockifyDeletedEntityFactory>
     * @use BelongsToOrganization<ClockifyDeletedEntity>
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
            'entity_type' => SyncEntityType::class,
            'deleted_at' => 'datetime',
            'applied_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    /**
     * The workspace the deletion belongs to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
