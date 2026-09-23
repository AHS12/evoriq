<?php

namespace App\DTOs\Developer;

final readonly class CommandRunDTO
{
    public function __construct(
        public string $action,
        public string $command,
    ) {}

    public static function forAction(string $action, string $command): self
    {
        return new self($action, $command);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'command' => $this->command,
        ];
    }
}
