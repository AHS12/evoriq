<?php

namespace App\Services\Clockify;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The single entry point for talking to the Clockify REST API.
 *
 * Application code must never issue arbitrary Clockify HTTP requests. All
 * traffic flows through this client so authentication, rate limiting,
 * pagination and retries stay centralized.
 */
class ClockifyClient
{
    protected ?string $apiKey = null;

    protected ?string $addonToken = null;

    protected ?string $baseUrl = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected ClockifyRateLimiter $rateLimiter,
        protected ClockifyPaginator $paginator,
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
     * Perform a GET request against the Clockify API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = [], string $connectionKey = 'default'): Response
    {
        return $this->rateLimiter->attempt(
            $connectionKey,
            fn (): Response => $this->request()->get($uri, $query),
        );
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
