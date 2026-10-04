<?php

namespace App\Models;

use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyEntityChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A raw entry from Clockify's Entity Changes feed, queued for processing
 * (SYNC-01, SYNC-06). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property SyncEntityType $entity_type
 * @property string $clockify_id
 * @property EntityChangeType $change_type
 * @property Carbon $source_at
 * @property Carbon $detected_at
 * @property Carbon|null $processed_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'entity_type', 'clockify_id',
    'change_type', 'source_at', 'detected_at', 'processed_at', 'raw_data',
])]
class ClockifyEntityChange extends Model
{
    /**
     * @use HasFactory<ClockifyEntityChangeFactory>
     * @use BelongsToOrganization<ClockifyEntityChange>
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
            'change_type' => EntityChangeType::class,
            'source_at' => 'datetime',
            'detected_at' => 'datetime',
            'processed_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    /**
     * The workspace the change belongs to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
