<?php

namespace App\DTOs\Backup;

use App\Enums\BackupRunStatus;
use App\Enums\BackupRunType;
use Illuminate\Http\Request;

final readonly class BackupRunFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?BackupRunStatus $status = null,
        public ?BackupRunType $type = null,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->string('search')->toString() ?: null,
            status: $request->filled('status') ? BackupRunStatus::tryFrom((string) $request->input('status')) : null,
            type: $request->filled('type') ? BackupRunType::tryFrom((string) $request->input('type')) : null,
            orderBy: (string) $request->input('order_by', 'created_at'),
            orderDirection: (string) $request->input('order_direction', 'desc'),
            perPage: $request->integer('per_page', 10),
        );
    }
}
