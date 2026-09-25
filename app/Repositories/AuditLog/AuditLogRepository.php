<?php

namespace App\Repositories\AuditLog;

use App\DTOs\AuditLog\AuditLogFilterDTO;
use App\Models\AuditActivity;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by. Cursor pagination requires a
     * total order, so the primary key always breaks ties.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'id'];

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?AuditActivity
    {
        return AuditActivity::query()->with($relations)->find($id);
    }

    /**
     * @param  array<int, string>  $relations
     * @return Builder<AuditActivity>
     */
    public function buildFilterQuery(AuditLogFilterDTO $filters, array $relations = []): Builder
    {
        $query = AuditActivity::query()->with($relations);

        if ($filters->search !== null && $filters->search !== '') {
            $query->where('description', 'like', "%{$filters->search}%");
        }

        if ($filters->channel !== null) {
            $query->where('log_name', $filters->channel->value);
        }

        if ($filters->event !== null) {
            $query->where('event', $filters->event->value);
        }

        if ($filters->subjectType !== null && $filters->subjectType !== '') {
            $query->where('subject_type', $filters->subjectType);
        }

        if ($filters->causerId !== null) {
            $query->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $filters->causerId);
        }

        if ($filters->dateFrom !== null && $filters->dateFrom !== '') {
            $query->where('created_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null && $filters->dateTo !== '') {
            $query->where('created_at', '<=', $filters->dateTo);
        }

        return $query;
    }

    /**
     * @return CursorPaginator<int, AuditActivity>
     */
    public function cursorPaginate(AuditLogFilterDTO $filters, int $perPage = 25, ?string $cursor = null): CursorPaginator
    {
        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        $query = $this->buildFilterQuery($filters, ['causer', 'subject'])->orderBy($orderBy, $orderDirection);

        if ($orderBy !== 'id') {
            $query->orderBy('id', $orderDirection);
        }

        return $query->cursorPaginate($perPage, cursor: $cursor);
    }

    public function deleteOlderThan(string $channel, CarbonInterface $cutoff): int
    {
        return AuditActivity::query()
            ->where('log_name', $channel)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
