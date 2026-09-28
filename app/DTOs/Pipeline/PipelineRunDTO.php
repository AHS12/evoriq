<?php

namespace App\DTOs\Pipeline;

use App\Enums\PipelineFailureReason;
use App\Http\Resources\Pipeline\PipelineRunResource;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use Illuminate\Support\Collection;

/**
 * The aggregated run contract handed to {@see PipelineRunResource}.
 */
final readonly class PipelineRunDTO
{
    /**
     * @param  array<int, PipelineStageDTO>  $stages
     * @param  Collection<int, PipelineEvent>  $timeline
     * @param  array<int, PipelineIssueGroupDTO>  $issues
     */
    public function __construct(
        public DataProcessingJob $job,
        public PipelineProgressDTO $progress,
        public PipelineTimingDTO $timing,
        public array $stages,
        public Collection $timeline,
        public ?PipelineFailureReason $failureReason,
        public int $skipped,
        public array $issues = [],
        public ?string $correlationId = null,
    ) {}
}
