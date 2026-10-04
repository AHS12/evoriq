<?php

namespace App\Repositories\Contracts;

use App\Enums\ApiUsageWindowType;
use App\Models\ClockifyApiUsage;
use Carbon\CarbonInterface;

interface ClockifyApiUsageRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyApiUsage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyApiUsage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyApiUsage $usage, array $data): ClockifyApiUsage;

    public function delete(ClockifyApiUsage $usage): bool;

    /**
     * Find the budget window a request falls into, if it exists.
     */
    public function findWindow(
        int $connectionId,
        ?int $workspaceId,
        ApiUsageWindowType $windowType,
        CarbonInterface $windowStartedAt,
    ): ?ClockifyApiUsage;

    /**
     * Return the window for the given natural key, creating it when missing.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreateWindow(array $attributes, array $values): ClockifyApiUsage;

    /**
     * Re-read a window under a row lock so concurrent reservations serialize.
     */
    public function lockWindow(int $id): ?ClockifyApiUsage;

    /**
     * Atomically add to a window's used count.
     */
    public function incrementRequests(ClockifyApiUsage $usage, int $amount = 1): ClockifyApiUsage;
}
