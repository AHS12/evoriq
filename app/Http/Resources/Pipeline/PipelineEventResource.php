<?php

namespace App\Http\Resources\Pipeline;

use App\Models\PipelineEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry in a run's timeline (PIPE-02/06).
 *
 * @mixin PipelineEvent
 */
class PipelineEventResource extends JsonResource
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
            'sequence' => $this->sequence,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'level' => $this->level->value,
            'stage' => $this->stage,
            'message' => $this->message,
            'context' => $this->context,
            'progress' => $this->progress,
            'attempt' => $this->attempt,
            'duration_ms' => $this->duration_ms,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
