<?php

namespace App\DTOs\Role;

use Illuminate\Http\Request;

final readonly class RoleFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public string $orderBy = 'name',
        public string $orderDirection = 'asc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            orderBy: (string) $request->input('order_by', 'name'),
            orderDirection: (string) $request->input('order_direction', 'asc'),
            perPage: $request->integer('per_page', 15),
        );
    }
}
