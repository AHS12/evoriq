<?php

namespace App\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Holds per-execution audit context (correlation id, request metadata).
 *
 * Every audit row written during one HTTP request or queue job shares the
 * same correlation id, so the UI can group related events. Registered as a
 * singleton; started by the AuditRequestContext middleware (HTTP) or by jobs
 * that call startForJob().
 */
final class AuditContext
{
    private ?string $correlationId = null;

    /** @var array<string, mixed> */
    private array $context = [];

    public function start(Request $request): void
    {
        $this->correlationId = (string) Str::uuid();
        $this->context = [
            'correlation_id' => $this->correlationId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ];
    }

    public function startForJob(string $job): void
    {
        $this->correlationId = (string) Str::uuid();
        $this->context = [
            'correlation_id' => $this->correlationId,
            'source' => $job,
        ];
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public function flush(): void
    {
        $this->correlationId = null;
        $this->context = [];
    }
}
