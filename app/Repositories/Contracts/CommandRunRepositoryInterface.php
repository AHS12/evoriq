<?php

namespace App\Repositories\Contracts;

use App\DTOs\Developer\CommandRunFilterDTO;
use App\Models\CommandRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface CommandRunRepositoryInterface
{
    public function findById(int $id): ?CommandRun;

    /**
     * @return Builder<CommandRun>
     */
    public function buildFilterQuery(CommandRunFilterDTO $filters): Builder;

    /**
     * @return LengthAwarePaginator<int, CommandRun>
     */
    public function paginate(CommandRunFilterDTO $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CommandRun;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CommandRun $model, array $data): CommandRun;

    public function delete(CommandRun $model): bool;

    /**
     * @return Collection<int, CommandRun>
     */
    public function recent(int $limit = 10): Collection;
}
