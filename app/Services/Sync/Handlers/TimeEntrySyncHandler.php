<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\TimeEntrySyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Ingests the time-entry fact (ENT-07) from the **per-user** endpoint
 * `GET /workspaces/{ws}/user/{userId}/time-entries`. One job covers one user and
 * one range (SYNC-03 fan-out). Duration is derived from `timeInterval`
 * (Clockify exposes no duration field); running/untracked entries have no end.
 * Tags, project and task Clockify ids are resolved by
 * {@see TimeEntrySyncRepository}.
 */
class TimeEntrySyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TIME_ENTRY;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::FACT;
    }

    protected function repositoryClass(): string
    {
        return TimeEntrySyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        if ($context->userId === null) {
            throw new RuntimeException('A time-entry job requires a per-user context.');
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
        $interval = is_array($raw['timeInterval'] ?? null) ? $raw['timeInterval'] : [];
        $start = $this->date($interval['start'] ?? null);

        if ($start === null) {
            // Running/untracked entries have a null interval; without a start
            // there is nothing to anchor the fact, so skip the row (ENT-00).
            throw new InvalidArgumentException('The time entry has no start time.');
        }

        $end = $this->date($interval['end'] ?? null);
        $currency = $context->workspace->currency;
        $billable = $this->rate($raw['hourlyRate'] ?? null, $currency);
        $cost = $this->rate($raw['costRate'] ?? null, $currency);

        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'description' => $this->string($raw['description'] ?? null),
            'start_at' => $start,
            'end_at' => $end,
            'duration_seconds' => $this->duration($start, $end),
            'billable' => (bool) ($raw['billable'] ?? false),
            'type' => $this->string($raw['type'] ?? null),
            'time_zone' => $this->string($raw['timeZone'] ?? null),
            'is_locked' => (bool) ($raw['isLocked'] ?? false),
            'is_in_progress' => $this->inProgress($raw, $end),
            'approval_status' => $this->string($raw['approvalStatus'] ?? null),
            'cost_amount' => $cost['amount'],
            'cost_currency' => $cost['currency'],
            'billable_amount' => $billable['amount'],
            'billable_currency' => $billable['currency'],
            'clockify_created_at' => $this->date($raw['createdAt'] ?? null),
            'clockify_updated_at' => $this->date($raw['updatedAt'] ?? null),
            'user_clockify_id' => $this->string($raw['userId'] ?? null) ?? $context->userId,
            'project_clockify_id' => $this->string($raw['projectId'] ?? null),
            'task_clockify_id' => $this->string($raw['taskId'] ?? null),
            'tag_clockify_ids' => $this->tagIds($raw),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    private function duration(CarbonInterface $start, ?CarbonInterface $end): ?int
    {
        if ($end === null) {
            return null;
        }

        return (int) round($start->diffInSeconds($end, true));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function inProgress(array $raw, ?CarbonInterface $end): bool
    {
        if ((bool) ($raw['isInProgress'] ?? $raw['currentlyRunning'] ?? false)) {
            return true;
        }

        $interval = $raw['timeInterval'] ?? null;

        return is_array($interval) && ! empty($interval['start']) && empty($interval['end']);
    }

    /**
     * `null` when the payload omits `tagIds` (leave joins untouched); an array
     * (possibly empty) means the entry's tag set is authoritative.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, string>|null
     */
    private function tagIds(array $raw): ?array
    {
        if (! array_key_exists('tagIds', $raw) || ! is_array($raw['tagIds'])) {
            return null;
        }

        return array_values(array_filter(
            $raw['tagIds'],
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, currency}` shape.
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
