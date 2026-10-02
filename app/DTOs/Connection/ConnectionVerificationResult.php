<?php

namespace App\DTOs\Connection;

use App\Enums\ApiErrorCode;
use App\Services\Connection\PlanProfile;

/**
 * The typed result of a connection verification (CONN-02), consumed by the
 * connect UI (CONN-04). Never carries credentials.
 */
final readonly class ConnectionVerificationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $workspaces
     * @param  array{name: string|null, email: string|null}|null  $account
     */
    public function __construct(
        public bool $ok,
        public ?ApiErrorCode $errorCode = null,
        public ?string $errorMessage = null,
        public ?int $retryAfter = null,
        public ?PlanProfile $profile = null,
        public array $workspaces = [],
        public ?array $account = null,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $workspaces
     * @param  array{name: string|null, email: string|null}|null  $account
     */
    public static function success(PlanProfile $profile, array $workspaces, ?array $account = null): self
    {
        return new self(ok: true, profile: $profile, workspaces: $workspaces, account: $account);
    }

    public static function failure(ApiErrorCode $code, string $message, ?int $retryAfter = null): self
    {
        return new self(
            ok: false,
            errorCode: $code,
            errorMessage: $message,
            retryAfter: $retryAfter,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'error' => $this->ok ? null : [
                'code' => $this->errorCode?->value,
                'label' => $this->errorCode?->label(),
                'hint' => $this->errorCode?->hint(),
                'action' => $this->errorCode?->action(),
                'retry_after' => $this->retryAfter,
            ],
            'profile' => $this->profile?->toArray(),
            'workspaces' => $this->workspaces,
            'account' => $this->account,
        ];
    }
}
