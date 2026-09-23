<?php

namespace App\DTOs\Upload;

use App\Enums\UploadType;
use Illuminate\Http\Request;

final readonly class UploadFilterDTO
{
    public function __construct(
        public ?UploadType $type = null,
        public ?string $search = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            type: $request->filled('type') ? UploadType::tryFrom((string) $request->input('type')) : null,
            search: $request->string('search')->toString() ?: null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );
    }
}
