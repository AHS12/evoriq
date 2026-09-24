<?php

namespace App\DTOs\Notification;

use App\Enums\NotificationPriority;
use Illuminate\Http\Request;

final readonly class NotificationFeedFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public bool $unreadOnly = false,
        public ?NotificationPriority $priority = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 20,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $perPage = (int) $request->integer('per_page', (int) config('notification.feed.per_page', 20));
        $max = (int) config('notification.feed.max_per_page', 100);

        return new self(
            search: $request->string('search')->toString() ?: null,
            unreadOnly: $request->boolean('unread'),
            priority: $request->filled('priority')
                ? NotificationPriority::tryFrom((string) $request->input('priority'))
                : null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: max(1, min($perPage, $max)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'unread' => $this->unreadOnly,
            'priority' => $this->priority?->value,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
            'per_page' => $this->perPage,
        ];
    }
}
