<?php

namespace App\Services\Connection;

use App\DTOs\Connection\ConnectionDTO;
use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Owns the Clockify connection lifecycle (CONN-01): credential writes are
 * transactional, endpoint URLs are resolved from the region, and every
 * transition is audited. Credentials are never logged.
 */
class ClockifyConnectionService
{
    public function __construct(
        protected ClockifyConnectionRepositoryInterface $connections,
        protected AuditLogService $audit,
    ) {}

    /**
     * Create a connection from a validated DTO.
     */
    public function create(ConnectionDTO $dto): ClockifyConnection
    {
        $connection = DB::transaction(fn (): ClockifyConnection => $this->connections->create([
            ...$dto->toArray(),
            'base_url' => $dto->region->baseUrl(),
            'reports_base_url' => $dto->region->reportsBaseUrlFor($dto->subdomain),
            'status' => ConnectionStatus::ACTIVE->value,
            'created_by' => auth()->id(),
        ]));

        $this->audit->record(
            AuditEvent::CONNECTION_CREATED,
            $connection,
            ['name' => $connection->name, 'region' => $connection->region->value],
            actor: auth()->user(),
            description: __('Clockify connection created'),
        );

        return $connection;
    }

    /**
     * Update a connection and re-resolve its endpoint URLs.
     */
    public function update(ClockifyConnection $connection, ConnectionDTO $dto): ClockifyConnection
    {
        $updated = DB::transaction(fn (): ClockifyConnection => $this->connections->update($connection, [
            ...$dto->toArray(),
            'base_url' => $dto->region->baseUrl(),
            'reports_base_url' => $dto->region->reportsBaseUrlFor($dto->subdomain),
        ]));

        $this->audit->record(
            AuditEvent::CONNECTION_UPDATED,
            $updated,
            ['name' => $updated->name, 'region' => $updated->region->value],
            actor: auth()->user(),
            description: __('Clockify connection updated'),
        );

        return $updated;
    }

    /**
     * Disable a connection without deleting its credentials.
     */
    public function disable(ClockifyConnection $connection): ClockifyConnection
    {
        $updated = DB::transaction(fn (): ClockifyConnection => $this->connections->update($connection, [
            'status' => ConnectionStatus::DISABLED->value,
        ]));

        $this->audit->record(
            AuditEvent::CONNECTION_DISABLED,
            $updated,
            ['name' => $updated->name],
            actor: auth()->user(),
            description: __('Clockify connection disabled'),
        );

        return $updated;
    }
}
