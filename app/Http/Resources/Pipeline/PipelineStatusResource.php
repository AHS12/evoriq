<?php

namespace App\Http\Resources\Pipeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the shared global pipeline snapshot (PIPE-08). The service already
 * returns the final array, so this resource only types/normalizes it for
 * Inertia and future JSON reuse.
 */
class PipelineStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
