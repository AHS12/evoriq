<?php

namespace App\Services\Sync;

use App\DTOs\Sync\ApiUsageSnapshot;
use App\Enums\ApiUsageWindowType;
use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyApiUsageRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The durable, plan-aware API budget (SYNC-02). Clockify returns no rate-limit
 * headers, so Evoriq accounts for every request itself: Free plans get an hourly
 * window, paid plans a per-second one, and the last slice of each window is
 * reserved by a safety factor so we never overrun.
 */
class ApiUsageService
{
    public function __construct(
        protected ClockifyApiUsageRepositoryInterface $usage,
    ) {}

    /**
     * A read-only view of the window a request would fall into.
     */
    public function snapshot(ClockifyConnection $connection, ?ClockifyWorkspace $workspace = null): ApiUsageSnapshot
    {
        $windowType = $this->windowType($connection);
        $start = $this->windowStart($windowType);
        $window = $this->usage->findWindow($connection->id, $workspace?->id, $windowType, $start);

        $limit = $window !== null ? $window->limit_requests : $this->limit($connection);
        $used = $window !== null ? $window->requests_used : 0;

        // Prefer what Clockify last told us; otherwise derive it from our count.
        $remaining = $window?->requests_remaining !== null
            ? max(0, (int) $window->requests_remaining)
            : max(0, $limit - $used);

        $endsAt = $window !== null ? $window->window_ends_at : $this->windowEnd($windowType, $start);

        return new ApiUsageSnapshot(
            windowType: $windowType,
            used: $used,
            limit: $limit,
            remaining: $remaining,
            windowEndsAt: $endsAt,
            resetsIn: $this->resetsIn($endsAt),
            lastRequestAt: $window?->last_request_at,
        );
    }

    /**
     * Atomically reserve `n` requests in the current window, or refuse when the
     * window cannot afford them. Concurrent callers serialize on the row lock.
     */
    public function reserve(ClockifyConnection $connection, ?ClockifyWorkspace $workspace = null, int $n = 1): bool
    {
        $n = max(1, $n);

        return DB::transaction(function () use ($connection, $workspace, $n): bool {
            $window = $this->rollover($connection, $workspace);
            $locked = $this->usage->lockWindow($window->id) ?? $window;
            $effective = $this->effectiveLimit($connection, $locked->limit_requests);

            if ($locked->requests_used + $n > $effective) {
                return false;
            }

            $this->usage->incrementRequests($locked, $n);

            return true;
        });
    }

    /**
     * Record that a request completed. `reserve()` already counted it, so this
     * only stamps freshness and mirrors an exhausted signal (e.g. a 429).
     */
    public function record(ClockifyConnection $connection, ?ClockifyWorkspace $workspace = null, ?int $statusCode = null): void
    {
        $window = $this->rollover($connection, $workspace);

        $data = ['last_request_at' => now()];

        if ($statusCode === 429) {
            $data['requests_remaining'] = 0;
        }

        $this->usage->update($window, $data);
    }

    /**
     * Ensure the window for "now" exists and return it.
     */
    public function rollover(ClockifyConnection $connection, ?ClockifyWorkspace $workspace = null): ClockifyApiUsage
    {
        $windowType = $this->windowType($connection);
        $start = $this->windowStart($windowType);
        $limit = $this->limit($connection);

        return $this->usage->firstOrCreateWindow(
            [
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'workspace_id' => $workspace?->id,
                'window_type' => $windowType->value,
                'window_started_at' => $start,
            ],
            [
                'window_ends_at' => $this->windowEnd($windowType, $start),
                'limit_requests' => $limit,
                'requests_used' => 0,
            ],
        );
    }

    /**
     * Whether the current window can afford `n` more requests. Read-only: the
     * planner uses this to gate dispatch without spending budget.
     */
    public function canAfford(ClockifyConnection $connection, ?ClockifyWorkspace $workspace = null, int $n = 1): bool
    {
        $windowType = $this->windowType($connection);
        $window = $this->usage->findWindow(
            $connection->id,
            $workspace?->id,
            $windowType,
            $this->windowStart($windowType),
        );

        $limit = $window !== null ? $window->limit_requests : $this->limit($connection);
        $used = $window !== null ? $window->requests_used : 0;

        return $used + max(1, $n) <= $this->effectiveLimit($connection, $limit);
    }

    private function windowType(ClockifyConnection $connection): ApiUsageWindowType
    {
        return $connection->isFreePlan() ? ApiUsageWindowType::HOUR : ApiUsageWindowType::SECOND;
    }

    private function limit(ClockifyConnection $connection): int
    {
        $profile = $connection->rateProfile();

        return max(1, (int) $profile['limit']);
    }

    private function effectiveLimit(ClockifyConnection $connection, int $limit): int
    {
        $factor = (float) config('clockify.budget_safety_factor', 0.9);
        $factor = min(1.0, max(0.0, $factor));

        return max(1, (int) floor($limit * $factor));
    }

    private function windowStart(ApiUsageWindowType $windowType, ?CarbonInterface $at = null): CarbonInterface
    {
        $at ??= now();

        return $windowType->isHourly()
            ? $at->copy()->startOfHour()
            : $at->copy()->startOfSecond();
    }

    private function windowEnd(ApiUsageWindowType $windowType, CarbonInterface $start): CarbonInterface
    {
        return $windowType->isHourly()
            ? $start->copy()->addHour()
            : $start->copy()->addSecond();
    }

    private function resetsIn(CarbonInterface $endsAt): int
    {
        return max(0, $endsAt->getTimestamp() - now()->getTimestamp());
    }
}
