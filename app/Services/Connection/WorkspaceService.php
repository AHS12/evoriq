<?php

namespace App\Services\Connection;

use App\Enums\AuditEvent;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Clockify\WorkspaceMapper;
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
        protected WorkspaceMapper $mapper,
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
        $attributes = $this->mapper->map($workspace);
        $id = $attributes['clockify_id'];

        return [
            ...$attributes,
            'active' => $activeId === null || $id === $activeId,
            'raw_data' => $workspace,
            'synced_at' => now(),
        ];
    }
}
