<?php

namespace App\Repositories\Contracts;

use App\DTOs\AuditLog\AuditLogFilterDTO;
use App\Models\AuditActivity;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

interface AuditLogRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?AuditActivity;

    /**
     * @param  array<int, string>  $relations
     * @return Builder<AuditActivity>
     */
    public function buildFilterQuery(AuditLogFilterDTO $filters, array $relations = []): Builder;

    /**
     * Keyset-paginated, newest-first listing (audit rows are append-only).
     *
     * @return CursorPaginator<int, AuditActivity>
     */
    public function cursorPaginate(AuditLogFilterDTO $filters, int $perPage = 25, ?string $cursor = null): CursorPaginator;

    /**
     * Delete entries of one channel older than the cutoff.
     */
    public function deleteOlderThan(string $channel, CarbonInterface $cutoff): int;
}
