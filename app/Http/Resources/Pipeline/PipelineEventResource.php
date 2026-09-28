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
        [$context, $truncated] = $this->boundedContext();

        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'level' => $this->level->value,
            'stage' => $this->stage,
            'message' => $this->message,
            'context' => $context,
            'context_truncated' => $truncated,
            'progress' => $this->progress,
            'attempt' => $this->attempt,
            'duration_ms' => $this->duration_ms,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }

    /**
     * Bound very large context payloads so a runaway run cannot ship megabytes
     * of JSON to every timeline poll. Over the limit the context is sent as a
     * truncated JSON string with `context_truncated: true`.
     *
     * @return array{0: array<string, mixed>|string|null, 1: bool}
     */
    private function boundedContext(): array
    {
        $context = $this->context;

        if ($context === null || $context === []) {
            return [$context, false];
        }

        $encoded = json_encode($context);
        $limit = max(1, (int) config('pipeline.context_limit', 20000));

        if (is_string($encoded) && strlen($encoded) > $limit) {
            return [mb_substr($encoded, 0, $limit), true];
        }

        return [$context, false];
    }
}
