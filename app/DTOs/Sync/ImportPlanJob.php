<?php

namespace App\DTOs\Sync;

use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Enums\SyncPriority;
use Carbon\CarbonInterface;

/**
 * A single planned unit of work (SYNC-03): one entity over one range, for one
 * user when the entity fans out. Serializable into `clockify_sync_runs.plan`.
 */
final readonly class ImportPlanJob
{
    public function __construct(
        public SyncEntityType $entityType,
        public SyncPhase $phase,
        public SyncPriority $priority,
        public ?CarbonInterface $rangeStart = null,
        public ?CarbonInterface $rangeEnd = null,
        public ?string $userId = null,
        public int $pageSize = 200,
        public int $estimatedRequests = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType->value,
            'entity_label' => $this->entityType->label(),
            'phase' => $this->phase->value,
            'priority' => $this->priority->value,
            'range_start' => $this->rangeStart?->toIso8601String(),
            'range_end' => $this->rangeEnd?->toIso8601String(),
            'user_id' => $this->userId,
            'page_size' => $this->pageSize,
            'estimated_requests' => $this->estimatedRequests,
        ];
    }
}
