<?php

namespace App\DTOs\DataProcessingJob;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;
use App\Http\Requests\Export\StoreExportRequest;

final readonly class DataProcessingJobDTO
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public DataEntity $entityType,
        public ExportFormat $format = ExportFormat::XLSX,
        public DataProcessingJobType $type = DataProcessingJobType::EXPORT,
        public array $filters = [],
        public ?int $totalItems = null,
    ) {}

    public static function fromRequest(StoreExportRequest $request): self
    {
        $validated = $request->validated();

        $filters = $validated['filters'] ?? null;

        return new self(
            entityType: DataEntity::from((string) $validated['entity_type']),
            format: ExportFormat::from((string) $validated['format']),
            filters: is_array($filters) ? $filters : [],
            totalItems: isset($validated['total_items']) ? (int) $validated['total_items'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'entity_type' => $this->entityType->value,
            'format' => $this->format->value,
            'filters' => $this->filters,
            'total_items' => $this->totalItems,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
