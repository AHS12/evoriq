<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\TimeEntryRateSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use RuntimeException;

/**
 * Derives the historical per-entry rate fact (ENT-08) from hydrated time
 * entries (`hourlyRate`/`costRate`). There is no dedicated rate endpoint, so it
 * reuses the per-user entries endpoint the planner already funds as its own
 * `TIME_ENTRY_RATE` fact job. Rows key on the entry; many entries have no
 * explicit rate, so nulls are stored and analytics falls back on demand
 * (ANA-08). Deletion cascades with the entry (policy `NONE`).
 */
class TimeEntryRateSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TIME_ENTRY_RATE;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::FACT;
    }

    protected function repositoryClass(): string
    {
        return TimeEntryRateSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        if ($context->userId === null) {
            throw new RuntimeException('A time-entry rate job requires a per-user context.');
        }

        return "/workspaces/{$context->workspace->clockify_id}/user/{$context->userId}/time-entries";
    }

    protected function query(SyncContext $context, int $page): array
    {
        $query = [
            'page' => $page,
            'page-size' => $context->pageSize,
            'hydrated' => 'true',
        ];

        if ($context->rangeStart !== null) {
            $query['start'] = $context->rangeStart->toIso8601String();
        }

        if ($context->rangeEnd !== null) {
            $query['end'] = $context->rangeEnd->toIso8601String();
        }

        return $query;
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        $currency = $context->workspace->currency;
        $billable = $this->rate($raw['hourlyRate'] ?? null, $currency);
        $cost = $this->rate($raw['costRate'] ?? null, $currency);

        return [
            'time_entry_clockify_id' => (string) ($raw['id'] ?? ''),
            'billable_rate_amount' => $billable['amount'],
            'billable_rate_currency' => $billable['currency'],
            'cost_rate_amount' => $cost['amount'],
            'cost_rate_currency' => $cost['currency'],
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::NONE;
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, currency, since}` shape.
     *
     * @return array{amount: float|null, currency: string|null}
     */
    private function rate(mixed $value, ?string $fallbackCurrency): array
    {
        $amount = is_array($value) ? ($value['amount'] ?? null) : $value;
        $amount = is_numeric($amount) ? (float) $amount : null;

        $currency = is_array($value)
            ? $this->string($value['currency'] ?? $value['currencyCode'] ?? null)
            : null;

        return [
            'amount' => $amount,
            'currency' => $currency ?? ($amount !== null ? $fallbackCurrency : null),
        ];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
