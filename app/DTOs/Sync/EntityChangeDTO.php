<?php

namespace App\DTOs\Sync;

use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use Carbon\CarbonInterface;

/**
 * A single change reported by the Clockify Entity Changes feed (SYNC-06).
 */
final readonly class EntityChangeDTO
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public SyncEntityType $entityType,
        public string $clockifyId,
        public EntityChangeType $changeType,
        public ?CarbonInterface $sourceAt = null,
        public array $raw = [],
    ) {}
}
