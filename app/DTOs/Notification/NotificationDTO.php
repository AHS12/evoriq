<?php

namespace App\DTOs\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationTargetType;
use App\Enums\NotificationType;
use Illuminate\Support\Carbon;

final readonly class NotificationDTO
{
    /**
     * @param  array<int, NotificationTargetDTO>  $targets
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public NotificationType|string $type,
        public string $title,
        public ?string $body = null,
        public ?NotificationPriority $priority = null,
        public ?string $actionUrl = null,
        public ?array $data = null,
        public ?string $groupKey = null,
        public ?Carbon $expiresAt = null,
        public array $targets = [],
        public ?int $createdBy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? NotificationType::SYSTEM_ANNOUNCEMENT->value;

        /** @var array<int, array<string, mixed>|NotificationTargetDTO> $targets */
        $targets = $data['targets'] ?? [];

        return new self(
            type: $type instanceof NotificationType ? $type : (string) $type,
            title: (string) ($data['title'] ?? ''),
            body: isset($data['body']) ? (string) $data['body'] : null,
            priority: $data['priority'] ?? null,
            actionUrl: isset($data['action_url']) ? (string) $data['action_url'] : null,
            data: $data['data'] ?? null,
            groupKey: isset($data['group_key']) ? (string) $data['group_key'] : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            targets: array_map(
                static fn (mixed $target): NotificationTargetDTO => $target instanceof NotificationTargetDTO
                    ? $target
                    : NotificationTargetDTO::fromArray((array) $target),
                $targets,
            ),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
        );
    }

    /**
     * The notification row attributes (excluding targets).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->typeValue(),
            'priority' => $this->resolvedPriority()->value,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'data' => $this->data,
            'group_key' => $this->groupKey,
            'expires_at' => $this->expiresAt,
        ];
    }

    public function typeValue(): string
    {
        return $this->type instanceof NotificationType ? $this->type->value : $this->type;
    }

    public function resolvedPriority(): NotificationPriority
    {
        if ($this->priority !== null) {
            return $this->priority;
        }

        return $this->type instanceof NotificationType
            ? $this->type->defaultPriority()
            : NotificationPriority::INFO;
    }

    /**
     * The target rows, defaulting to a single `ALL` target when none are set.
     *
     * @return array<int, array<string, mixed>>
     */
    public function targetsToArray(): array
    {
        $targets = $this->targets;

        if ($targets === []) {
            $targets = [new NotificationTargetDTO(NotificationTargetType::ALL)];
        }

        return array_map(
            static fn (NotificationTargetDTO $target): array => $target->toArray(),
            $targets,
        );
    }
}
