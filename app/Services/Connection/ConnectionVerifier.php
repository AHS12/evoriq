<?php

namespace App\Services\Connection;

use App\DTOs\Connection\ConnectionVerificationResult;
use App\Enums\ApiErrorCode;
use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Clockify\ClockifyClient;
use App\Services\Clockify\ClockifyErrorMapper;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Probes a Clockify credential and detects its capability profile (CONN-02):
 * `/user` + `/workspaces`, the plan/rate budget and subdomain.
 *
 * `inspect()` is side-effect free (the connect-flow preview, CONN-04);
 * `verify()` persists the profile and upserts workspaces for a stored
 * connection. Credentials are never logged.
 */
class ConnectionVerifier
{
    public function __construct(
        protected ClockifyClient $client,
        protected ClockifyErrorMapper $errors,
        protected ClockifyConnectionRepositoryInterface $connections,
        protected WorkspaceService $workspaces,
        protected AuditLogService $audit,
    ) {}

    /**
     * Probe raw credentials without persisting anything (CONN-04 preview).
     */
    public function inspect(string $apiKey, ?string $addonToken, ApiRegion $region): ConnectionVerificationResult
    {
        $probe = $this->probe($apiKey, $addonToken, $region->baseUrl(), 'inspect');

        if (! $probe['ok'] || $probe['default'] === null) {
            return ConnectionVerificationResult::failure(
                $probe['error']['code'],
                $probe['error']['message'],
                $probe['error']['retry_after'],
            );
        }

        return ConnectionVerificationResult::success(
            PlanProfile::fromWorkspace($probe['default']),
            $this->workspaces->options($probe['workspaces'], $probe['active_id']),
            $this->account($probe['user']),
        );
    }

    /**
     * Verify a stored connection: persist its profile and upsert workspaces.
     */
    public function verify(ClockifyConnection $connection): ConnectionVerificationResult
    {
        $probe = $this->probe(
            $connection->api_key,
            $connection->addon_token,
            $connection->base_url,
            (string) $connection->id,
        );

        if (! $probe['ok'] || $probe['default'] === null) {
            return $this->fail($connection, $probe['error']);
        }

        $persisted = $this->persist($connection, $probe);

        $this->audit->record(
            AuditEvent::CONNECTION_VERIFIED,
            $persisted,
            ['plan' => PlanProfile::fromWorkspace($probe['default'])->plan, 'workspaces' => count($probe['workspaces'])],
            actor: auth()->user(),
            description: __('Clockify connection verified'),
        );

        return $this->result($persisted, $probe);
    }

    /**
     * Rotate a connection's credentials (CONN-05): probe the new key first and
     * only replace the stored credentials once it verifies. A failed probe
     * leaves the existing (working) key untouched.
     */
    public function rotate(
        ClockifyConnection $connection,
        string $apiKey,
        ?string $addonToken,
        ApiRegion $region,
        ?string $subdomain,
    ): ConnectionVerificationResult {
        $probe = $this->probe($apiKey, $addonToken, $region->baseUrl(), (string) $connection->id);

        if (! $probe['ok'] || $probe['default'] === null) {
            $this->audit->record(
                AuditEvent::CONNECTION_VALIDATION_FAILED,
                $connection,
                ['action' => 'rotate_key', 'code' => $probe['error']['code']->value],
                actor: auth()->user(),
                channel: AuditLogName::SECURITY,
                description: __('Clockify connection key rotation failed'),
            );

            return ConnectionVerificationResult::failure(
                $probe['error']['code'],
                $probe['error']['message'],
                $probe['error']['retry_after'],
            );
        }

        $persisted = $this->persist($connection, $probe, [
            'api_key' => $apiKey,
            'addon_token' => $addonToken,
            'region' => $region->value,
            'base_url' => $region->baseUrl(),
            'subdomain' => $subdomain,
        ]);

        $this->audit->record(
            AuditEvent::CONNECTION_KEY_ROTATED,
            $persisted,
            ['plan' => PlanProfile::fromWorkspace($probe['default'])->plan],
            actor: auth()->user(),
            channel: AuditLogName::SECURITY,
            description: __('Clockify connection key rotated'),
        );

        return $this->result($persisted, $probe);
    }

    /**
     * Persist a successful probe: profile, endpoints, active workspace and the
     * discovered workspaces. Optional overrides carry rotated credentials.
     *
     * @param  array{ok: bool, error: array{code: ApiErrorCode, message: string, retry_after: int|null}, user: array<string, mixed>, workspaces: array<int, array<string, mixed>>, active_id: string|null, default: array<string, mixed>|null}  $probe
     * @param  array<string, mixed>  $overrides
     */
    private function persist(ClockifyConnection $connection, array $probe, array $overrides = []): ClockifyConnection
    {
        $default = (array) $probe['default'];
        $profile = PlanProfile::fromWorkspace($default);
        $activeId = $probe['active_id'];
        $subdomain = $overrides['subdomain'] ?? $this->resolveSubdomain($connection, $default);
        $region = isset($overrides['region']) ? ApiRegion::from((string) $overrides['region']) : $connection->region;

        unset($overrides['subdomain']);

        return DB::transaction(function () use ($connection, $default, $profile, $activeId, $subdomain, $region, $overrides, $probe): ClockifyConnection {
            $updated = $this->connections->update($connection, [
                ...$overrides,
                'status' => ConnectionStatus::ACTIVE->value,
                'last_verified_at' => now(),
                'last_error' => null,
                'feature_subscription_type' => $default['featureSubscriptionType'] ?? null,
                'features' => $default['features'] ?? null,
                'webhook_limit' => $profile->webhookLimit,
                'requests_per_hour' => $profile->requestsPerHour,
                'requests_per_second' => $profile->requestsPerSecond,
                'workspace_id' => $activeId,
                'subdomain' => $subdomain,
                'base_url' => $region->baseUrl(),
                'reports_base_url' => $region->reportsBaseUrlFor($subdomain),
            ]);

            $this->workspaces->syncFromConnection($updated, $probe['workspaces'], $activeId);

            return $updated;
        });
    }

    /**
     * Build the credential-free success result for a persisted probe.
     *
     * @param  array{ok: bool, error: array{code: ApiErrorCode, message: string, retry_after: int|null}, user: array<string, mixed>, workspaces: array<int, array<string, mixed>>, active_id: string|null, default: array<string, mixed>|null}  $probe
     */
    private function result(ClockifyConnection $connection, array $probe): ConnectionVerificationResult
    {
        $profile = PlanProfile::fromWorkspace((array) $probe['default']);

        $workspaces = $this->workspaces->forConnection($connection)
            ->map(fn ($workspace): array => [
                'id' => $workspace->id,
                'clockify_id' => $workspace->clockify_id,
                'name' => $workspace->name,
                'subdomain' => $workspace->subdomain,
                'currency' => $workspace->currency,
                'time_zone' => $workspace->time_zone,
                'feature_subscription_type' => $workspace->feature_subscription_type,
                'active' => $workspace->active,
            ])
            ->all();

        return ConnectionVerificationResult::success($profile, $workspaces, $this->account($probe['user']));
    }

    /**
     * Probe `/user` + `/workspaces`; never persists.
     *
     * @return array{ok: bool, error: array{code: ApiErrorCode, message: string, retry_after: int|null}, user: array<string, mixed>, workspaces: array<int, array<string, mixed>>, active_id: string|null, default: array<string, mixed>|null}
     */
    private function probe(string $apiKey, ?string $addonToken, string $baseUrl, string $connectionKey): array
    {
        $client = $this->client->forCredentials($apiKey, $addonToken, $baseUrl);

        try {
            $userResponse = $client->get('/user', connectionKey: $connectionKey);
        } catch (Throwable $exception) {
            return $this->failedProbe($this->errors->fromThrowable($exception));
        }

        if ($userResponse->failed()) {
            return $this->failedProbe($this->errors->fromResponse($userResponse));
        }

        try {
            $workspaceResponse = $client->get('/workspaces', connectionKey: $connectionKey);
        } catch (Throwable $exception) {
            return $this->failedProbe($this->errors->fromThrowable($exception));
        }

        if ($workspaceResponse->failed()) {
            return $this->failedProbe($this->errors->fromResponse($workspaceResponse));
        }

        $user = (array) $userResponse->json();

        /** @var array<int, array<string, mixed>> $rawWorkspaces */
        $rawWorkspaces = array_values(array_filter(
            (array) $workspaceResponse->json(),
            static fn (mixed $workspace): bool => is_array($workspace) && ! empty($workspace['id']),
        ));

        if ($rawWorkspaces === []) {
            return $this->failedProbe([
                'code' => ApiErrorCode::CLOCKIFY_API_ERROR,
                'message' => __('No workspaces were returned.'),
                'retry_after' => null,
            ]);
        }

        $activeId = $this->activeWorkspaceId($user);

        return [
            'ok' => true,
            'error' => ['code' => ApiErrorCode::CLOCKIFY_UNKNOWN, 'message' => '', 'retry_after' => null],
            'user' => $user,
            'workspaces' => $rawWorkspaces,
            'active_id' => $activeId,
            'default' => $this->defaultWorkspace($rawWorkspaces, $activeId),
        ];
    }

    /**
     * @param  array{code: ApiErrorCode, message: string, retry_after: int|null}  $error
     * @return array{ok: bool, error: array{code: ApiErrorCode, message: string, retry_after: int|null}, user: array<string, mixed>, workspaces: array<int, array<string, mixed>>, active_id: string|null, default: array<string, mixed>|null}
     */
    private function failedProbe(array $error): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'user' => [],
            'workspaces' => [],
            'active_id' => null,
            'default' => null,
        ];
    }

    /**
     * Persist the failure and return the typed error (never the credential).
     *
     * @param  array{code: ApiErrorCode, message: string, retry_after: int|null}  $error
     */
    private function fail(ClockifyConnection $connection, array $error): ConnectionVerificationResult
    {
        $this->connections->update($connection, [
            'status' => ConnectionStatus::INVALID->value,
            'last_verified_at' => now(),
            'last_error' => $error['message'],
        ]);

        $this->audit->record(
            AuditEvent::CONNECTION_VALIDATION_FAILED,
            $connection,
            ['code' => $error['code']->value],
            actor: auth()->user(),
            description: __('Clockify connection validation failed'),
        );

        return ConnectionVerificationResult::failure($error['code'], $error['message'], $error['retry_after']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $workspaces
     * @return array<string, mixed>
     */
    private function defaultWorkspace(array $workspaces, ?string $activeId): array
    {
        if ($activeId !== null) {
            foreach ($workspaces as $workspace) {
                if ((string) $workspace['id'] === $activeId) {
                    return $workspace;
                }
            }
        }

        return $workspaces[0];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function activeWorkspaceId(array $user): ?string
    {
        $active = $user['activeWorkspace'] ?? $user['defaultWorkspace'] ?? null;

        if (is_string($active)) {
            return $active;
        }

        if (is_array($active) && isset($active['id'])) {
            return (string) $active['id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array{name: string|null, email: string|null}
     */
    private function account(array $user): array
    {
        return [
            'name' => isset($user['name']) ? (string) $user['name'] : null,
            'email' => isset($user['email']) ? (string) $user['email'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $default
     */
    private function resolveSubdomain(ClockifyConnection $connection, array $default): ?string
    {
        $subdomain = $default['subdomain'] ?? null;

        if (is_array($subdomain)) {
            $subdomain = $subdomain['name'] ?? null;
        }

        if (! is_string($subdomain) || $subdomain === '') {
            $subdomain = $connection->subdomain;
        }

        return is_string($subdomain) && $subdomain !== '' ? $subdomain : null;
    }
}
