<?php

namespace App\Services\Connection;

use App\DTOs\Connection\ConnectionDTO;
use App\DTOs\Connection\ConnectionVerificationResult;
use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Owns the Clockify connection lifecycle (CONN-01, CONN-05): credential writes
 * are transactional, endpoint URLs are resolved from the region, and every
 * transition is audited. Credentials are never logged.
 */
class ClockifyConnectionService
{
    public function __construct(
        protected ClockifyConnectionRepositoryInterface $connections,
        protected AuditLogService $audit,
        protected ConnectionVerifier $verifier,
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
     * Rename a connection, optionally re-pointing it at a region/subdomain
     * (CONN-05). Endpoint URLs are re-resolved; the credential is untouched.
     */
    public function rename(
        ClockifyConnection $connection,
        string $name,
        ?ApiRegion $region = null,
        ?string $subdomain = null,
    ): ClockifyConnection {
        $effectiveRegion = $region ?? $connection->region;
        $effectiveSubdomain = $subdomain ?? $connection->subdomain;

        $data = [
            'name' => $name,
            'region' => $effectiveRegion->value,
            'subdomain' => $effectiveSubdomain,
            'base_url' => $effectiveRegion->baseUrl(),
            'reports_base_url' => $effectiveRegion->reportsBaseUrlFor($effectiveSubdomain),
        ];

        $updated = DB::transaction(fn (): ClockifyConnection => $this->connections->update($connection, $data));

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
     * Re-verify a stored connection (CONN-05); delegates to the verifier so the
     * detected profile, status and workspaces are refreshed in one pass.
     */
    public function reverify(ClockifyConnection $connection): ConnectionVerificationResult
    {
        return $this->verifier->verify($connection);
    }

    /**
     * Rotate a connection's API key (CONN-05). The new key is verified before it
     * replaces the stored credential; a failed probe keeps the old key.
     */
    public function rotateKey(
        ClockifyConnection $connection,
        string $apiKey,
        ?string $addonToken,
        ApiRegion $region,
        ?string $subdomain = null,
    ): ConnectionVerificationResult {
        return $this->verifier->rotate($connection, $apiKey, $addonToken, $region, $subdomain);
    }

    /**
     * Disable a connection without deleting its credentials (CONN-05). Disabled
     * connections are skipped by the scheduler (`findActive`).
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
            channel: AuditLogName::SECURITY,
            description: __('Clockify connection disabled'),
        );

        return $updated;
    }

    /**
     * Re-enable a disabled connection (CONN-05).
     */
    public function enable(ClockifyConnection $connection): ClockifyConnection
    {
        $updated = DB::transaction(fn (): ClockifyConnection => $this->connections->update($connection, [
            'status' => ConnectionStatus::ACTIVE->value,
        ]));

        $this->audit->record(
            AuditEvent::CONNECTION_ENABLED,
            $updated,
            ['name' => $updated->name],
            actor: auth()->user(),
            channel: AuditLogName::SECURITY,
            description: __('Clockify connection enabled'),
        );

        return $updated;
    }
}
