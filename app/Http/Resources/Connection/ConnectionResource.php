<?php

namespace App\Http\Resources\Connection;

use App\Models\ClockifyConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The safe, credential-free projection of a Clockify connection (CONN-01).
 * `api_key` / `addon_token` are never present.
 *
 * @mixin ClockifyConnection
 */
class ConnectionResource extends JsonResource
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
            'name' => $this->name,
            'region' => $this->region->value,
            'region_label' => $this->region->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'plan' => [
                'subscription_type' => $this->feature_subscription_type,
                'is_free' => $this->isFreePlan(),
                'rate' => $this->rateProfile(),
            ],
            'workspace' => [
                'id' => $this->workspace_id,
                'subdomain' => $this->subdomain,
            ],
            'webhook_limit' => $this->webhook_limit,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
