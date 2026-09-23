<?php

namespace App\DTOs\Developer;

use App\Enums\CommandRunStatus;
use Illuminate\Http\Request;

final readonly class CommandRunFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?CommandRunStatus $status = null,
        public ?string $action = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            status: $request->filled('status') ? CommandRunStatus::tryFrom((string) $request->input('status')) : null,
            action: $request->string('action')->toString() ?: null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: $request->integer('per_page', 10),
        );
    }
}
