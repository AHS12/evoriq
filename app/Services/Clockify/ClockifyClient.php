<?php

namespace App\Services\Clockify;

use App\Events\Sync\ApiBudgetExhausted;
use App\Exceptions\SyncBudgetExhausted;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\ApiUsageService;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The single entry point for talking to the Clockify REST API.
 *
 * Application code must never issue arbitrary Clockify HTTP requests. All
 * traffic flows through this client so authentication, rate limiting,
 * pagination and retries stay centralized. When bound to a connection (see
 * `forConnection`), every request also reserves and records against the durable
 * API budget (SYNC-02).
 */
class ClockifyClient
{
    protected ?string $apiKey = null;

    protected ?string $addonToken = null;

    protected ?string $baseUrl = null;

    protected ?ClockifyConnection $connection = null;

    protected ?ClockifyWorkspace $workspace = null;

    /**
     * When true, a request that cannot reserve budget throws
     * {@see SyncBudgetExhausted} instead of blocking the worker. Sync jobs set
     * this so a long Free-plan import parks and resumes at the window reset
     * (SYNC-13/SYNC-20).
     */
    protected bool $deferBudget = false;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected ClockifyRateLimiter $rateLimiter,
        protected ClockifyPaginator $paginator,
        protected ApiUsageService $usage,
        protected array $config,
    ) {}

    /**
     * Return a client bound to the given Clockify credentials.
     */
    public function forCredentials(
        ?string $apiKey,
        ?string $addonToken = null,
        ?string $baseUrl = null,
    ): static {
        $client = clone $this;
        $client->apiKey = $apiKey;
        $client->addonToken = $addonToken;
        $client->baseUrl = $baseUrl;

        return $client;
    }

    /**
     * Return a client bound to a stored connection, so every request is
     * accounted against that connection's budget (SYNC-02).
     */
    public function forConnection(
        ClockifyConnection $connection,
        ?ClockifyWorkspace $workspace = null,
    ): static {
        $client = clone $this;
        $client->apiKey = $connection->api_key;
        $client->addonToken = $connection->addon_token;
        $client->baseUrl = $connection->base_url;
        $client->connection = $connection;
        $client->workspace = $workspace;

        return $client;
    }

    /**
     * Perform a GET request against the Clockify API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = [], string $connectionKey = 'default'): Response
    {
        $this->awaitBudget();

        $key = $this->connection !== null ? (string) $this->connection->id : $connectionKey;

        $response = $this->rateLimiter->attempt(
            $key,
            fn (): Response => $this->request()->get($uri, $query),
        );

        if ($this->connection !== null) {
            $this->usage->record($this->connection, $this->workspace, $response->status());
        }

        return $response;
    }

    /**
     * Iterate every page of a Clockify list endpoint.
     *
     * @param  array<string, mixed>  $query
     * @return Generator<int, array<int, mixed>>
     */
    public function paginate(string $uri, array $query = [], string $connectionKey = 'default'): Generator
    {
        return $this->paginator->pages(
            $uri,
            $query,
            fn (string $uri, array $query): Response => $this->get($uri, $query, $connectionKey),
        );
    }

    /**
     * Toggle budget-deferral mode (SYNC-13). In deferral mode the client never
     * sleeps for the window: it throws {@see SyncBudgetExhausted} so the sync
     * runner can park the job and resume later.
     */
    public function deferBudget(bool $defer = true): static
    {
        $this->deferBudget = $defer;

        return $this;
    }

    /**
     * Block until the current window can afford a request, emitting a
     * budget-wait event each time it has to wait (SYNC-02). In deferral mode it
     * throws instead of waiting.
     */
    protected function awaitBudget(): void
    {
        if ($this->connection === null) {
            return;
        }

        while (! $this->usage->reserve($this->connection, $this->workspace)) {
            $snapshot = $this->usage->snapshot($this->connection, $this->workspace);

            event(new ApiBudgetExhausted($this->connection, $this->workspace, $snapshot));

            if ($this->deferBudget) {
                throw new SyncBudgetExhausted($snapshot->resetsIn);
            }

            $this->waitForBudget($snapshot->resetsIn);
        }
    }

    /**
     * Sleep until the budget window resets (capped so a lost worker can never
     * block indefinitely).
     */
    protected function waitForBudget(int $seconds): void
    {
        $seconds = max(1, $seconds);
        $cap = (int) config('clockify.budget_wait_max_seconds', 3600);

        usleep(min($seconds, $cap) * 1_000_000);
    }

    protected function request(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl ?? $this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->retry(
                $this->config['retry']['times'],
                $this->config['retry']['sleep'],
                throw: false,
            );

        if ($this->apiKey !== null) {
            $request->withHeader('X-Api-Key', $this->apiKey);
        }

        if ($this->addonToken !== null) {
            $request->withHeader('X-Addon-Token', $this->addonToken);
        }

        return $request;
    }
}
