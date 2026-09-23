<?php

namespace App\Http\Resources\Export;

use App\Models\DataProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DataProcessingJob
 */
class DataProcessingJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'entity_type' => $this->entity_type?->value,
            'format' => $this->format?->value,
            'filters' => $this->filters,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'total_items' => $this->total_items,
            'processed_items' => $this->processed_items,
            'success_count' => $this->success_count,
            'error_count' => $this->error_count,
            'error_message' => $this->error_message,
            'progress_percentage' => $this->progressPercentage(),
            'downloadable' => $this->isDownloadable(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
