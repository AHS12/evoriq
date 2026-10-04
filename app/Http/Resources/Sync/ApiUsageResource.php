<?php

namespace App\Http\Resources\Sync;

use App\DTOs\Sync\ApiUsageSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The credential-free projection of the current API budget window (SYNC-02),
 * consumed by the navbar indicator/popover (SYNC-17).
 *
 * @mixin ApiUsageSnapshot
 */
class ApiUsageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiUsageSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'window_type' => $snapshot->windowType->value,
            'used' => $snapshot->used,
            'limit' => $snapshot->limit,
            'remaining' => $snapshot->remaining,
            'resets_at' => $snapshot->windowEndsAt->toIso8601String(),
            'resets_in' => $snapshot->resetsIn,
            'last_request_at' => $snapshot->lastRequestAt?->toIso8601String(),
            'plan' => $snapshot->windowType->isHourly() ? 'free' : 'paid',
            'low' => $snapshot->isLow(),
            'exhausted' => $snapshot->isExhausted(),
        ];
    }
}
