<?php

namespace App\Services\Sync\Handlers;

use App\Enums\ClockifyMembershipType;
use App\Enums\ClockifyUserStatus;
use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\ClockifyMembershipRepositoryInterface;
use App\Repositories\Entity\UserSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use App\Support\Clockify\Iso8601Duration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Ingests the people dimension (ENT-02) from
 * `GET /workspaces/{ws}/users?memberships=ALL`, and derives each user's
 * workspace membership + rates from the same payload (there is no separate
 * membership entity type). Capacity (`workCapacity` ISO-8601, `workingDays`
 * JSON string) and status are mapped tolerantly; absent fields never erase
 * stored values.
 */
class UserSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
        protected ClockifyMembershipRepositoryInterface $memberships,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::USER;
    }

    /**
     * ENT-13 policy: soft-delete the user (entries keep their name) and remove
     * the ended workspace memberships.
     */
    public function delete(SyncContext $context, string $clockifyId): void
    {
        parent::delete($context, $clockifyId);

        $this->memberships->deleteForUser($context->workspace, $clockifyId);
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return UserSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}/users";
    }

    protected function query(SyncContext $context, int $page): array
    {
        return [
            'page' => $page,
            'page-size' => $context->pageSize,
            'memberships' => 'ALL',
        ];
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        $settings = is_array($raw['settings'] ?? null) ? $raw['settings'] : [];

        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'email' => $this->string($raw['email'] ?? null),
            'status' => ClockifyUserStatus::fromApi($raw['status'] ?? null)?->value,
            'profile_picture_url' => $this->string($raw['profilePicture'] ?? $raw['profilePictureUrl'] ?? null),
            'timezone' => $this->string($settings['timeZone'] ?? $raw['timeZone'] ?? null),
            'week_start' => $this->string($settings['weekStart'] ?? $raw['weekStart'] ?? null),
            'working_days' => $this->workingDays($settings['workingDays'] ?? $raw['workingDays'] ?? null),
            'work_capacity' => Iso8601Duration::toSeconds($settings['workCapacity'] ?? $raw['workCapacity'] ?? null),
            'raw_data' => $raw,
            'memberships' => $this->memberships($raw),
            'custom_field_values' => $this->customFieldValues($raw),
        ];
    }

    /**
     * The user's custom-field values (ENT-11), derived from the same users-list
     * payload. `null` when the payload omits them (leave existing values
     * untouched); an array (possibly empty) is authoritative.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>|null
     */
    private function customFieldValues(array $raw): ?array
    {
        $list = $raw['customFieldValues'] ?? $raw['userCustomFieldValues'] ?? null;

        if (! is_array($list)) {
            return null;
        }

        $values = [];

        foreach ($list as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $fieldId = $this->string($entry['customFieldId'] ?? $entry['fieldId'] ?? null);

            if ($fieldId === null) {
                continue;
            }

            $values[] = [
                'custom_field_clockify_id' => $fieldId,
                'value' => $entry['value'] ?? null,
                'raw_data' => $entry,
            ];
        }

        return $values;
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * Derive the user's workspace membership + rates (ENT-02 owns workspace
     * membership; project/user-group memberships land in ENT-04/ENT-12).
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function memberships(array $raw): array
    {
        $memberships = [];
        $list = $raw['memberships'] ?? null;

        if (is_array($list)) {
            foreach ($list as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (ClockifyMembershipType::fromApi($entry['membershipType'] ?? null) !== ClockifyMembershipType::WORKSPACE) {
                    continue;
                }

                $memberships[] = $this->workspaceMembership($entry);
            }
        }

        // Some responses omit the memberships array and carry rates at the top
        // level; fall back to those only when we have no workspace row yet.
        if ($memberships === []) {
            $membership = $this->workspaceMembership($raw);

            if ($membership['hourly_rate_amount'] !== null || $membership['cost_rate_amount'] !== null) {
                $memberships[] = $membership;
            }
        }

        return $memberships;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function workspaceMembership(array $source): array
    {
        $hourly = $this->rate($source['hourlyRate'] ?? null);
        $cost = $this->rate($source['costRate'] ?? null);

        return [
            'membership_type' => ClockifyMembershipType::WORKSPACE->value,
            'membership_status' => $this->string($source['membershipStatus'] ?? $source['status'] ?? null),
            'target_type' => null,
            'target_id' => null,
            'hourly_rate_amount' => $hourly['amount'],
            'hourly_rate_currency' => $hourly['currency'],
            'cost_rate_amount' => $cost['amount'],
            'cost_rate_currency' => $cost['currency'],
            'effective_from' => $this->effectiveFrom($source),
            'raw_data' => $source,
        ];
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, currency}` shape.
     *
     * @return array{amount: float|null, currency: string|null}
     */
    private function rate(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'amount' => is_numeric($value['amount'] ?? null) ? (float) $value['amount'] : null,
                'currency' => $this->string($value['currency'] ?? $value['currencyCode'] ?? null),
            ];
        }

        return [
            'amount' => is_numeric($value) ? (float) $value : null,
            'currency' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function effectiveFrom(array $source): ?CarbonInterface
    {
        $direct = $this->parseDate(
            $source['since'] ?? $source['effectiveFrom'] ?? $source['effective_from'] ?? null,
        );

        if ($direct !== null) {
            return $direct;
        }

        foreach (['hourlyRate', 'costRate'] as $rateKey) {
            $rate = $source[$rateKey] ?? null;

            if (is_array($rate)) {
                $parsed = $this->parseDate($rate['since'] ?? $rate['effectiveFrom'] ?? null);

                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?CarbonInterface
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

    /**
     * `workingDays` arrives as a JSON string from the profile endpoint, but some
     * responses already decode it to an array.
     *
     * @return array<int|string, mixed>|null
     */
    private function workingDays(mixed $value): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($value) ? $value : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
