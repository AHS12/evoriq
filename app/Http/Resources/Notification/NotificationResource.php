<?php

namespace App\Http\Resources\Notification;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = NotificationType::tryFrom((string) $this->type);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $type?->label() ?? Str::headline((string) $this->type),
            'icon' => $type?->icon(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->action_url,
            'data' => $this->data,
            'group_key' => $this->group_key,
            'is_read' => (bool) ($this->is_read ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_diff' => $this->created_at?->diffForHumans(),
        ];
    }
}
