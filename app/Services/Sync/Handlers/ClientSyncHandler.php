<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\ClientSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Ingests the client dimension (ENT-03) from
 * `GET /workspaces/{ws}/clients`. Clockify's `archived` flag is preserved
 * separately from our `deleted_at` (an upstream deletion, SYNC-07); the client
 * currency falls back to the workspace currency when the client omits one.
 */
class ClientSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::CLIENTS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return ClientSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/clients";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'email' => $this->string($raw['email'] ?? null),
            'address' => $this->string($raw['address'] ?? null),
            'note' => $this->string($raw['note'] ?? null),
            'currency_code' => $this->string($raw['currencyCode'] ?? $raw['currency'] ?? null)
                ?? $context->workspace->currency,
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

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
