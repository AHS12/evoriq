<?php

namespace App\Http\Resources\Export;

use App\Models\DataProcessingJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

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
        $user = $request->user();

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'name' => $this->displayName(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'entity_type' => $this->entity_type?->value,
            'entity_label' => $this->entity_type?->label(),
            'entity_icon' => $this->entity_type?->icon(),
            'format' => $this->format?->value,
            'format_label' => $this->format?->label(),
            'filters' => $this->filters,
            'parameters' => $this->filters,
            'stage' => $this->stage,
            'progress' => [
                'total' => $this->total_items,
                'processed' => $this->processed_items,
                'percentage' => $this->progressPercentage(),
                'indeterminate' => $this->total_items === null,
            ],
            'counts' => [
                'created' => $this->success_count,
                'failed' => $this->error_count,
            ],
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'input_file_name' => $this->original_file_name,
            'total_items' => $this->total_items,
            'processed_items' => $this->processed_items,
            'success_count' => $this->success_count,
            'error_count' => $this->error_count,
            'error_message' => $this->error_message,
            'errors' => $this->errors ?? [],
            'progress_percentage' => $this->progressPercentage(),
            'artifacts' => $this->artifactList(),
            'download_url' => $this->isDownloadable() ? route('exports.download', $this) : null,
            'downloadable' => $this->isDownloadable(),
            'owner' => $this->whenLoaded('user', fn () => $this->user?->name),
            'duration' => $this->durationLabel(),
            'can' => [
                'cancel' => $user !== null && $this->isActive() && ! $this->cancellationRequested() && Gate::forUser($user)->allows('manage', $this),
                'retry' => $user !== null && $this->isFailed() && Gate::forUser($user)->allows('manage', $this),
                'duplicate' => $user !== null && Gate::forUser($user)->allows('manage', $this),
                'download' => $user !== null && Gate::forUser($user)->allows('download', $this),
                'delete' => $user !== null && Gate::forUser($user)->allows('delete', $this),
            ],
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
