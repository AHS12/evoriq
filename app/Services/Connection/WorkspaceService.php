<?php

namespace App\Services\Connection;

use App\Enums\AuditEvent;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the Clockify workspace dimension (CONN-03): idempotent upsert of
 * discovered workspaces and selecting the single active workspace per
 * connection. Normalization of raw Clockify payloads lives here.
 */
class WorkspaceService
{
    public function __construct(
        protected ClockifyWorkspaceRepositoryInterface $workspaces,
        protected ClockifyConnectionRepositoryInterface $connections,
        protected AuditLogService $audit,
    ) {}

    /**
     * Upsert the workspaces discovered during verification (CONN-02), keyed by
     * organization + Clockify id so re-verifying never duplicates rows.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return Collection<int, ClockifyWorkspace>
     */
    public function syncFromConnection(
        ClockifyConnection $connection,
        array $payload,
        ?string $activeId = null,
    ): Collection {
        return $this->workspaces->upsertMany(
            $connection,
            $this->normalizeMany($payload, $activeId),
        );
    }

    /**
     * Normalize raw Clockify workspace payloads into persistable rows.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function normalizeMany(array $payload, ?string $activeId = null): array
    {
        $activeId ??= isset($payload[0]['id']) ? (string) $payload[0]['id'] : null;

        return array_map(
            fn (array $workspace): array => $this->normalize($workspace, $activeId),
            $payload,
        );
    }

    /**
     * Normalize raw payloads into the credential-free option shape the connect
     * UI renders (CONN-04).
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function options(array $payload, ?string $activeId = null): array
    {
        return array_map(static fn (array $row): array => [
            'clockify_id' => $row['clockify_id'],
            'name' => $row['name'],
            'subdomain' => $row['subdomain'],
            'currency' => $row['currency'],
            'time_zone' => $row['time_zone'],
            'feature_subscription_type' => $row['feature_subscription_type'],
            'active' => $row['active'],
        ], $this->normalizeMany($payload, $activeId));
    }

    /**
     * @return Collection<int, ClockifyWorkspace>
     */
    public function forConnection(ClockifyConnection $connection): Collection
    {
        return $this->workspaces->forConnection($connection);
    }

    /**
     * Select the active workspace for a connection, turning all others off.
     */
    public function selectActive(ClockifyConnection $connection, string $clockifyId): ClockifyWorkspace
    {
        $workspace = $this->workspaces->findByClockifyId($connection->id, $clockifyId);

        if ($workspace === null) {
            throw new RuntimeException(__('The selected workspace could not be found.'));
        }

        return DB::transaction(function () use ($connection, $workspace, $clockifyId): ClockifyWorkspace {
            $this->workspaces->markActive($connection, $clockifyId);
            $this->connections->update($connection, ['workspace_id' => $clockifyId]);

            $this->audit->record(
                AuditEvent::WORKSPACE_SELECTED,
                $workspace,
                ['clockify_id' => $clockifyId, 'name' => $workspace->name],
                actor: auth()->user(),
                description: __('Workspace selected'),
            );

            return $workspace->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    private function normalize(array $workspace, ?string $activeId): array
    {
        $id = (string) $workspace['id'];

        return [
            'clockify_id' => $id,
            'name' => (string) ($workspace['name'] ?? 'Workspace'),
            'subdomain' => $this->subdomain($workspace),
            'currency' => $this->currency($workspace),
            'time_zone' => $this->string($workspace['timeZone'] ?? null)
                ?? $this->string(data_get($workspace, 'workspaceSettings.timeZone')),
            'week_start' => $this->string($workspace['weekStart'] ?? null)
                ?? $this->string(data_get($workspace, 'workspaceSettings.weekStart')),
            'feature_subscription_type' => $this->string($workspace['featureSubscriptionType'] ?? null),
            'features' => $this->stringList($workspace['features'] ?? null),
            'active' => $activeId === null || $id === $activeId,
            'raw_data' => $workspace,
            'synced_at' => now(),
        ];
    }

    /**
     * Clockify returns `subdomain` as a string (older) or `{ name, enabled }`.
     *
     * @param  array<string, mixed>  $workspace
     */
    private function subdomain(array $workspace): ?string
    {
        $subdomain = $workspace['subdomain'] ?? null;

        if (is_array($subdomain)) {
            return $this->string($subdomain['name'] ?? null);
        }

        return $this->string($subdomain);
    }

    /**
     * Prefer a top-level `currency`, else the default of the `currencies` list.
     *
     * @param  array<string, mixed>  $workspace
     */
    private function currency(array $workspace): ?string
    {
        $currency = $this->string($workspace['currency'] ?? null);

        if ($currency !== null) {
            return $currency;
        }

        $currencies = $workspace['currencies'] ?? null;

        if (is_array($currencies)) {
            foreach ($currencies as $entry) {
                if (is_array($entry) && ($entry['isDefault'] ?? false) && isset($entry['code'])) {
                    return (string) $entry['code'];
                }
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function stringList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
    }
}
