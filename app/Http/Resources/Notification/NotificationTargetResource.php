<?php

namespace App\Http\Resources\Notification;

use App\Models\NotificationTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationTarget
 */
class NotificationTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type->value,
            'target_id' => $this->target_id,
        ];
    }
}
