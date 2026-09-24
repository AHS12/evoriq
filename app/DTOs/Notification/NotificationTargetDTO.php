<?php

namespace App\DTOs\Notification;

use App\Enums\NotificationTargetType;

final readonly class NotificationTargetDTO
{
    public function __construct(
        public NotificationTargetType $type,
        public int|string|null $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? null;

        return new self(
            type: $type instanceof NotificationTargetType
                ? $type
                : NotificationTargetType::from((string) ($type ?? NotificationTargetType::ALL->value)),
            id: $data['id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_type' => $this->type->value,
            'target_id' => $this->id !== null ? (string) $this->id : null,
        ];
    }
}
