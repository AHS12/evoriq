<?php

namespace App\Services\Clockify\ChangeFeed;

use App\DTOs\Sync\EntityChangeDTO;
use App\DTOs\Sync\EntityChangePage;
use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\ClockifyWorkspace;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\Contracts\ChangeFeed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Clockify's experimental Entity Changes feed (SYNC-06), isolated behind the
 * `ChangeFeed` contract. Fetches created/updated/deleted for one type over a
 * range and tolerates the documented response-shape quirks.
 */
class ClockifyEntityChangesFeed implements ChangeFeed
{
    public function __construct(
        protected ClockifyClient $client,
    ) {}

    public function since(
        ClockifyWorkspace $workspace,
        CarbonInterface $from,
        CarbonInterface $to,
        SyncEntityType $type,
        int $page = 0,
    ): EntityChangePage {
        if (! (bool) config('clockify.change_feed.enabled', true)) {
            return new EntityChangePage([], $page, false);
        }

        $limit = $this->limit();
        $items = [];
        $hasMore = false;

        foreach (EntityChangeType::cases() as $changeType) {
            $rows = $this->fetch($workspace, $changeType, $type, $from, $to, $page, $limit);

            if (count($rows) >= $limit) {
                $hasMore = true;
            }

            foreach ($rows as $row) {
                if (! isset($row['id'])) {
                    continue;
                }

                $items[] = new EntityChangeDTO(
                    entityType: $type,
                    clockifyId: (string) $row['id'],
                    changeType: $changeType,
                    sourceAt: $this->sourceAt($row),
                    raw: $row,
                );
            }
        }

        return new EntityChangePage($items, $page, $hasMore);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(
        ClockifyWorkspace $workspace,
        EntityChangeType $changeType,
        SyncEntityType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        int $page,
        int $limit,
    ): array {
        $connection = $workspace->connection;

        if ($connection === null) {
            return [];
        }

        $response = $this->client
            ->forConnection($connection, $workspace)
            ->get(
                "/workspaces/{$workspace->clockify_id}/entities/".strtolower($changeType->value),
                [
                    'type' => $type->value,
                    'start' => $from->toIso8601String(),
                    'end' => $to->toIso8601String(),
                    'page' => $page,
                    'limit' => $limit,
                ],
            );

        if ($response->failed()) {
            return [];
        }

        return $this->normalize($response->json());
    }

    /**
     * Tolerate both documented `deleted` shapes: `{response: [...]}` and a bare
     * array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        if (isset($json['response']) && is_array($json['response'])) {
            $json = $json['response'];
        }

        return array_values(array_filter($json, static fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sourceAt(array $row): ?CarbonInterface
    {
        foreach (['updatedAt', 'createdAt', 'deletedAt', 'timestamp'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && $value !== '') {
                try {
                    return CarbonImmutable::parse($value);
                } catch (Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function limit(): int
    {
        return max(1, (int) config('clockify.change_feed.limit', 50));
    }
}
