<?php

namespace App\Services\Clockify;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Centralized, rate-limit-aware gate for every Clockify API request.
 *
 * Both manual and automatic synchronization share a single budget, keyed by
 * the Clockify connection, so independent entry points can never exceed the
 * source API's allowance.
 */
class ClockifyRateLimiter
{
    public function __construct(
        protected string $key,
        protected int $requestsPerSecond,
        protected ?int $burstLimit = null,
        protected float $cooldownSeconds = 0.0,
    ) {
        $this->burstLimit ??= $this->requestsPerSecond;
    }

    /**
     * Execute the given callback once a rate-limit slot is available.
     */
    public function attempt(string $connectionKey, Closure $callback): mixed
    {
        $limiterKey = $this->limiterKey($connectionKey);

        while (RateLimiter::tooManyAttempts($limiterKey, $this->burstLimit)) {
            $this->sleep(RateLimiter::availableIn($limiterKey));
        }

        RateLimiter::hit($limiterKey, 1);

        if ($this->cooldownSeconds > 0) {
            $this->sleep((int) ceil($this->cooldownSeconds));
        }

        return $callback();
    }

    /**
     * The identifier used for the shared budget of the given connection.
     */
    public function limiterKey(string $connectionKey): string
    {
        return 'clockify:'.$this->key.':'.$connectionKey;
    }

    protected function sleep(int $seconds): void
    {
        usleep(max(1, $seconds) * 1_000_000);
    }
}
