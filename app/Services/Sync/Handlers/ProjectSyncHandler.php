<?php

namespace App\Services\Sync\Handlers;

use App\Enums\ClockifyMembershipType;
use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Entity\ProjectSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use App\Support\Clockify\Iso8601Duration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Ingests the project dimension (ENT-04) from
 * `GET /workspaces/{ws}/projects`. The raw `clientId` is kept as a reserved
 * `client_clockify_id` for the repository to resolve to an internal `client_id`
 * (missing clients are tolerated), and each project's `memberships` list is
 * derived into project-member rows. Rates (`hourlyRate` → billable,
 * `costRate`) fall back to the workspace currency.
 */
class ProjectSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::PROJECTS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return ProjectSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/projects";
    }

    protected function query(SyncContext $context, int $page): array
    {
        return [
            'page' => $page,
            'page-size' => $context->pageSize,
            'memberships' => 'ALL',
            'hydrated' => 'true',
        ];
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        $currency = $context->workspace->currency;
        $hourly = $this->rate($raw['hourlyRate'] ?? null, $currency);
        $cost = $this->rate($raw['costRate'] ?? null, $currency);

        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'color' => $this->string($raw['color'] ?? null),
            'note' => $this->string($raw['note'] ?? null),
            'status' => $this->string($raw['status'] ?? null),
            'archived' => (bool) ($raw['archived'] ?? false),
            'archived_at' => $this->date($raw['archivedAt'] ?? null),
            'billable' => (bool) ($raw['billable'] ?? false),
            'public' => (bool) ($raw['public'] ?? true),
            'billable_rate_amount' => $hourly['amount'],
            'billable_rate_currency' => $hourly['currency'],
            'cost_rate_amount' => $cost['amount'],
            'cost_rate_currency' => $cost['currency'],
            'estimated_hours' => $this->estimatedHours($raw),
            'estimated_cost' => $this->numeric($raw['estimatedCost'] ?? null),
            // Resolved to an internal id by the repository (ENT-15 territory).
            'client_clockify_id' => $this->string($raw['clientId'] ?? null),
            'memberships' => $this->memberships($raw, $currency),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * Derive project-member rows. `null` means the payload did not include the
     * member list, so existing members must be left untouched; an empty array
     * means the project has no members and the set is replaced with none.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>|null
     */
    private function memberships(array $raw, ?string $currency): ?array
    {
        if (! array_key_exists('memberships', $raw) || ! is_array($raw['memberships'])) {
            return null;
        }

        $memberships = [];

        foreach ($raw['memberships'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $userId = $this->string($entry['userId'] ?? $entry['user'] ?? null);

            if ($userId === null) {
                continue;
            }

            $hourly = $this->rate($entry['hourlyRate'] ?? null, $currency);
            $cost = $this->rate($entry['costRate'] ?? null, $currency);

            $type = ClockifyMembershipType::fromApi($entry['membershipType'] ?? null);

            $memberships[] = [
                'user_clockify_id' => $userId,
                'membership_type' => ($type ?? ClockifyMembershipType::PROJECT)->value,
                'membership_status' => $this->string($entry['membershipStatus'] ?? null),
                'hourly_rate_amount' => $hourly['amount'],
                'hourly_rate_currency' => $hourly['currency'],
                'cost_rate_amount' => $cost['amount'],
                'cost_rate_currency' => $cost['currency'],
                'raw_data' => $entry,
            ];
        }

        return $memberships;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function estimatedHours(array $raw): ?float
    {
        $direct = $raw['estimatedHours'] ?? null;

        if (is_numeric($direct)) {
            return round((float) $direct, 2);
        }

        $estimate = $raw['estimate'] ?? null;

        if (is_array($estimate)) {
            $seconds = Iso8601Duration::toSeconds($estimate['estimate'] ?? null);

            if ($seconds !== null) {
                return round($seconds / 3600, 2);
            }
        }

        return null;
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, currency, since}` shape.
     *
     * @return array{amount: float|null, currency: string|null}
     */
    private function rate(mixed $value, ?string $fallbackCurrency): array
    {
        $amount = is_array($value)
            ? ($value['amount'] ?? null)
            : $value;

        $amount = is_numeric($amount) ? (float) $amount : null;

        $currency = is_array($value)
            ? $this->string($value['currency'] ?? $value['currencyCode'] ?? null)
            : null;

        return [
            'amount' => $amount,
            'currency' => $currency ?? ($amount !== null ? $fallbackCurrency : null),
        ];
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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
