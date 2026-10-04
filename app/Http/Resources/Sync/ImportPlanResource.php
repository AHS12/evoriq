<?php

namespace App\Http\Resources\Sync;

use App\DTOs\Sync\ImportPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The review-step projection of an import plan (SYNC-03), consumed by the
 * historical import wizard (PIPE-09).
 *
 * @mixin ImportPlan
 */
class ImportPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportPlan $plan */
        $plan = $this->resource;

        return $plan->toArray();
    }
}
