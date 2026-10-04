<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\UserGroupMemberRepositoryInterface;
use App\Repositories\Entity\UserGroupSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;

/**
 * Ingests the user-group (team) dimension (ENT-12) from
 * `GET /workspaces/{ws}/user-groups`. The group's `teamManagers[]` are stored as
 * raw Clockify ids; `userIds[]` are derived into the member join by
 * {@see UserGroupSyncRepository} (a missing `userIds` key leaves members
 * untouched).
 */
class UserGroupSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
        protected UserGroupMemberRepositoryInterface $members,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::USER_GROUPS;
    }

    /**
     * ENT-13 policy: soft-delete the group **and** remove its member rows.
     */
    public function delete(SyncContext $context, string $clockifyId): void
    {
        parent::delete($context, $clockifyId);

        $this->members->deleteForGroup($context->workspace, $clockifyId);
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return UserGroupSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/user-groups";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'status' => $this->string($raw['status'] ?? null),
            'team_managers' => $this->ids($raw['teamManagers'] ?? null),
            'user_clockify_ids' => $this->ids($raw['userIds'] ?? null),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * @return array<int, string>|null
     */
    private function ids(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
