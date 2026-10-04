<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\TimeEntryTagRepositoryInterface;
use App\Repositories\Entity\TagSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Ingests the tag dimension (ENT-06) from `GET /workspaces/{ws}/tags`. Tags are
 * related to time entries by the entry handler (ENT-07) through the join table.
 */
class TagSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
        protected TimeEntryTagRepositoryInterface $timeEntryTags,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TAGS;
    }

    /**
     * ENT-13 policy: soft-delete the tag **and** remove its entry joins so the
     * tag disappears from entries.
     */
    public function delete(SyncContext $context, string $clockifyId): void
    {
        parent::delete($context, $clockifyId);

        $this->timeEntryTags->deleteForTag($context->workspace, $clockifyId);
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return TagSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/tags";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'archived' => (bool) ($raw['archived'] ?? false),
            'archived_at' => $this->date($raw['archivedAt'] ?? null),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
