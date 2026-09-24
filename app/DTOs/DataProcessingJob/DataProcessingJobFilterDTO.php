<?php

namespace App\DTOs\DataProcessingJob;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use Illuminate\Http\Request;

final readonly class DataProcessingJobFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?DataProcessingJobType $type = null,
        public ?DataProcessingJobStatus $status = null,
        public ?DataEntity $entityType = null,
        public ?int $userId = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            type: $request->filled('type') ? DataProcessingJobType::tryFrom((string) $request->input('type')) : null,
            status: $request->filled('status') ? DataProcessingJobStatus::tryFrom((string) $request->input('status')) : null,
            entityType: $request->filled('entity_type') ? DataEntity::tryFrom((string) $request->input('entity_type')) : null,
            userId: $request->integer('user_id') ?: null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: $request->integer('per_page', (int) config('exports.pagination', 15)),
        );
    }

    /**
     * Return a copy of the filters scoped to a specific user (or unscoped when null).
     */
    public function scopedToUser(?int $userId): self
    {
        return new self(
            search: $this->search,
            type: $this->type,
            status: $this->status,
            entityType: $this->entityType,
            userId: $userId,
            orderBy: $this->orderBy,
            orderDirection: $this->orderDirection,
            perPage: $this->perPage,
        );
    }
}
