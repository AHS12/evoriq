<?php

namespace App\DTOs\DataProcessingJob;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;

/**
 * The single entry point every producer uses to queue background work.
 */
final readonly class JobRequest
{
    /**
     * @param  array<string, mixed>  $parameters  Entity-specific inputs (filters, date range…).
     */
    public function __construct(
        public DataEntity $entity,
        public DataProcessingJobType $type,
        public ?ExportFormat $format = null,
        public ?string $name = null,
        public array $parameters = [],
        public ?int $userId = null,
        public ?string $inputDisk = null,
        public ?string $inputPath = null,
        public ?string $originalFileName = null,
    ) {}
}
