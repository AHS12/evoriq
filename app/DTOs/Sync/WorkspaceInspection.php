<?php

namespace App\DTOs\Sync;

use App\Enums\SyncEntityType;

/**
 * The lightweight result of inspecting a workspace at plan time (SYNC-03):
 * the users a fact fan-out must iterate, plus optional volume hints per entity.
 */
final readonly class WorkspaceInspection
{
    /**
     * @param  array<int, array{id: string, name: string|null}>  $users
     * @param  array<string, int>  $volumes  Keyed by SyncEntityType value.
     */
    public function __construct(
        public array $users = [],
        public array $volumes = [],
    ) {}

    public function userCount(): int
    {
        return count($this->users);
    }

    public function volume(SyncEntityType $entity): ?int
    {
        return $this->volumes[$entity->value] ?? null;
    }
}
