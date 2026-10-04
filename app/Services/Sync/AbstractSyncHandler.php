<?php

namespace App\Services\Sync;

use App\Enums\ApiErrorCode;
use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Exceptions\ApiException;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\Contracts\SyncHandler;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Shared glue for every entity sync handler (ENT-00). A concrete handler only
 * declares its phase, endpoint, query, mapping and repository; this base class
 * owns the page fetch, the mapping template and the deletion policy, so the
 * fetch → raw → map → idempotent upsert contract is uniform across entities.
 *
 * The runner drives raw persistence, per-page transactions and checkpointing;
 * the base class keeps endpoint/mapping/deletion knowledge in one place.
 */
abstract class AbstractSyncHandler implements SyncHandler
{
    private ?SyncUpsertRepositoryInterface $repository = null;

    public function __construct(
        protected ClockifyClient $client,
    ) {}

    abstract public function entityType(): SyncEntityType;

    abstract public function phase(): SyncPhase;

    /**
     * The repository that idempotently persists this entity (SYNC-08).
     *
     * @return class-string<SyncUpsertRepositoryInterface>
     */
    abstract protected function repositoryClass(): string;

    /**
     * The workspace-scoped endpoint for one page of this entity. Handlers that
     * override `fetchPage()` (nested resources such as tasks, ENT-05) need not
     * implement it.
     */
    protected function endpoint(SyncContext $context): string
    {
        throw new RuntimeException('This handler must define an endpoint() or override fetchPage().');
    }

    /**
     * Map a single upstream item. Throw to skip a malformed row; the runner
     * records a warning and continues rather than failing the page (ENT-00).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    abstract protected function mapRow(array $raw, SyncContext $context): array;

    public function repository(): SyncUpsertRepositoryInterface
    {
        if ($this->repository === null) {
            /** @var SyncUpsertRepositoryInterface $repository */
            $repository = app($this->repositoryClass());

            $this->repository = $repository;
        }

        return $this->repository;
    }

    public function fetchPage(SyncContext $context, int $page): iterable
    {
        $endpoint = $this->endpoint($context);

        $response = $this->client
            ->forConnection($context->connection, $context->workspace)
            ->get($endpoint, $this->query($context, $page));

        if ($response->failed()) {
            throw ApiException::serverError(
                ApiErrorCode::CLOCKIFY_API_ERROR->value,
                "Clockify returned {$response->status()} for [{$endpoint}].",
            );
        }

        return $this->normalize($response);
    }

    /**
     * The query string for a page. Override to add filters (range, user, …);
     * `page`/`page-size` are the Clockify list-endpoint convention.
     *
     * @return array<string, mixed>
     */
    protected function query(SyncContext $context, int $page): array
    {
        return [
            'page' => $page,
            'page-size' => $context->pageSize,
        ];
    }

    public function map(array $raw, SyncContext $context): array
    {
        return $this->mapRow($raw, $context);
    }

    public function delete(SyncContext $context, string $clockifyId): void
    {
        match ($this->deletePolicy()) {
            SyncDeletePolicy::SOFT => $this->repository()->softDeleteByClockifyId($context->workspace, $clockifyId),
            SyncDeletePolicy::REPLACE => $this->repository()->deleteByClockifyId($context->workspace, $clockifyId),
            SyncDeletePolicy::NONE => null,
        };
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * Clockify returns a bare array for list resources and a single object for
     * detail endpoints; wrap the latter so the runner can iterate uniformly.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalize(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        if (array_is_list($json)) {
            return array_values(array_filter($json, static fn (mixed $row): bool => is_array($row)));
        }

        return [$json];
    }
}
