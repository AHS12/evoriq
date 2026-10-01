<?php

namespace App\DTOs\Connection;

use App\Enums\ApiRegion;
use App\Enums\ConnectionStatus;
use Illuminate\Http\Request;

/**
 * List filters for connections (CONN-01).
 */
final readonly class ConnectionFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?ConnectionStatus $status = null,
        public ?ApiRegion $region = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            status: $request->filled('status')
                ? ConnectionStatus::tryFrom((string) $request->input('status'))
                : null,
            region: $request->filled('region')
                ? ApiRegion::tryFrom((string) $request->input('region'))
                : null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );
    }
}
