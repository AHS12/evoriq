<?php

namespace App\Http\Resources\Connection;

use App\Models\ClockifyConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The credential-safe connection status summary (CONN-08) rendered by the
 * dashboard and the connections page. Never exposes key material.
 *
 * @mixin ClockifyConnection
 */
class ConnectionStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'workspace' => [
                'id' => $this->workspace_id,
                'subdomain' => $this->subdomain,
            ],
            'plan' => [
                'is_free' => $this->isFreePlan(),
                'subscription_type' => $this->feature_subscription_type,
                'rate' => $this->rateProfile(),
            ],
            'budget' => [
                'requests_per_hour' => $this->requests_per_hour,
                'requests_per_second' => $this->requests_per_second,
            ],
            'webhook_limit' => $this->webhook_limit,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
        ];
    }
}
