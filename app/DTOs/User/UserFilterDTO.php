<?php

namespace App\DTOs\User;

use App\Enums\UserStatus;
use Illuminate\Http\Request;

final readonly class UserFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $role = null,
        public ?UserStatus $status = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            role: $request->string('role')->toString() ?: null,
            status: $request->filled('status')
                ? UserStatus::tryFrom((string) $request->input('status'))
                : null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: max(1, min($request->integer('per_page', 15), 100)),
        );
    }
}
