<?php

namespace App\Http\Resources\Pipeline;

use App\DTOs\Pipeline\PipelineIssueGroupDTO;
use App\DTOs\Pipeline\PipelineRunDTO;
use App\DTOs\Pipeline\PipelineStageDTO;
use App\Enums\QueueName;
use App\Http\Resources\Export\DataProcessingJobResource;
use App\Registry\QueueRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * The single run contract the pipeline UI renders (PIPE-02).
 *
 * Superset of the legacy {@see DataProcessingJobResource}:
 * the aggregated contract plus the fields the Data Processing Center already
 * uses, so pages keep working while PIPE-05/06 migrate to the new shape.
 *
 * @mixin PipelineRunDTO
 */
class PipelineRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->job;
        $user = $request->user();
        $progress = $this->progress;
        $timing = $this->timing;
        $failure = $this->failureReason;

        $canManage = $user !== null && Gate::forUser($user)->allows('manage', $job);
        $canInspect = $user !== null
            && ($user->can('data-processing.manage') || $user->can('developer.view'));

        // Recovery is available for the two "we stopped early" states; a
        // completed run is re-run via duplicate instead.
        $recoverable = $job->isFailed() || $job->isCancelled();

        $abilities = [
            'cancel' => $user !== null && $job->isActive() && ! $job->cancellationRequested() && $canManage,
            'retry' => $user !== null && $recoverable && $canManage,
            'resume' => $user !== null && $recoverable && $canManage && $job->supportsResume(),
            'duplicate' => $canManage,
            'duplicate_soon' => $canManage && $job->isFailed() && $this->duplicateSoon,
            'download' => $user !== null && Gate::forUser($user)->allows('download', $job),
            'delete' => $user !== null && Gate::forUser($user)->allows('delete', $job),
        ];

        $failureAction = $failure?->action();

        if ($job->next_retry_at !== null && $job->next_retry_at->isFuture()) {
            $failureAction = 'wait';
        }

        return [
            // --- Run contract ---
            'id' => $job->id,
            'run_type' => $job->pipelineRunType()->value,
            'type' => $job->type->value,
            'status' => $job->status->value,
            'name' => $job->displayName(),
            'entity' => [
                'value' => $job->entity_type?->value,
                'label' => $job->entity_type?->label(),
                'icon' => $job->entity_type?->icon(),
            ],
            'stage' => $job->stage,
            'progress' => [
                'total' => $progress->total,
                'processed' => $progress->processed,
                'percentage' => $progress->percentage,
                'indeterminate' => $progress->indeterminate,
                'elapsed_seconds' => $progress->elapsedSeconds,
                'eta_seconds' => $progress->etaSeconds,
                'throughput_per_min' => $progress->throughputPerMin,
            ],
            'counts' => [
                'created' => $job->success_count,
                'updated' => 0,
                'failed' => $job->error_count,
                'skipped' => $this->skipped,
            ],
            'stages' => array_map(
                static fn (PipelineStageDTO $stage): array => $stage->toArray(),
                $this->stages,
            ),
            'attempts' => [
                'current' => $job->attempt,
                'label' => $job->attemptsLabel(),
                'max' => QueueRegistry::get(QueueName::HEAVY)->tries,
                'next_retry_at' => $job->next_retry_at?->toIso8601String(),
            ],
            'failure' => $failure === null ? null : [
                'reason' => $failure->value,
                'label' => $failure->label(),
                'hint' => $failure->hint(),
                'action' => $failureAction,
                'message' => $job->error_message,
            ],
            'timing' => [
                'dispatched_at' => $timing->dispatchedAt,
                'started_at' => $timing->startedAt,
                'completed_at' => $timing->completedAt,
                'duration_ms' => $timing->durationMs,
                'last_heartbeat_at' => $timing->lastHeartbeatAt,
                'stale' => $timing->stale,
            ],
            'abilities' => $abilities,
            'timeline' => PipelineEventResource::collection($this->timeline),
            'issues' => array_map(
                static fn (PipelineIssueGroupDTO $group): array => $group->toArray(),
                $this->issues,
            ),
            'advanced' => $canInspect ? [
                'correlation_id' => $this->correlationId,
                'raw' => $job->only([
                    'job_id', 'type', 'status', 'entity_type', 'format', 'filters',
                    'stage', 'attempt', 'total_items', 'processed_items',
                    'success_count', 'error_count', 'error_message',
                    'started_at', 'completed_at', 'created_at',
                ]),
            ] : null,

            // --- Legacy compatibility (Data Processing Center) ---
            'job_id' => $job->job_id,
            'type_label' => $job->type->label(),
            'type_icon' => $job->type->icon(),
            'status_label' => $job->status->label(),
            'entity_type' => $job->entity_type?->value,
            'entity_label' => $job->entity_type?->label(),
            'entity_icon' => $job->entity_type?->icon(),
            'format' => $job->format?->value,
            'format_label' => $job->format?->label(),
            'filters' => $job->filters,
            'parameters' => $job->filters,
            'file_name' => $job->file_name,
            'file_size' => $job->file_size,
            'input_file_name' => $job->original_file_name,
            'total_items' => $job->total_items,
            'processed_items' => $job->processed_items,
            'success_count' => $job->success_count,
            'error_count' => $job->error_count,
            'error_message' => $job->error_message,
            'errors' => $job->errors ?? [],
            'progress_percentage' => $job->progressPercentage(),
            'artifacts' => array_map(
                static fn (array $artifact): array => [
                    ...$artifact,
                    'download_url' => ($artifact['downloadable'] ?? false)
                        ? route('exports.download', $job)
                        : null,
                ],
                $job->artifactList(),
            ),
            'download_url' => $job->isDownloadable() ? route('exports.download', $job) : null,
            'downloadable' => $job->isDownloadable(),
            'owner' => $job->relationLoaded('user') ? $job->user?->name : null,
            'duration' => $job->durationLabel(),
            'can' => [
                'cancel' => $abilities['cancel'],
                'retry' => $abilities['retry'],
                'resume' => $abilities['resume'],
                'duplicate' => $abilities['duplicate'],
                'duplicate_soon' => $abilities['duplicate_soon'],
                'download' => $abilities['download'],
                'delete' => $abilities['delete'],
            ],
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }
}
