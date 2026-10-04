<?php

namespace App\Services\Connection;

use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;

/**
 * The credential-safe connection status for status cards (CONN-08): the active
 * connection for the current organization, or null when none is configured.
 */
class ConnectionStatusService
{
    public function __construct(
        protected ClockifyConnectionRepositoryInterface $connections,
    ) {}

    public function forOrganization(): ?ClockifyConnection
    {
        return $this->connections->findActive();
    }
}
