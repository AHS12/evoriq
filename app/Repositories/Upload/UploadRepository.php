<?php

namespace App\Repositories\Upload;

use App\DTOs\Upload\UploadFilterDTO;
use App\Enums\MediaCollection;
use App\Helpers\EloquentFilterHelper;
use App\Models\Upload;
use App\Repositories\Contracts\UploadRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UploadRepository implements UploadRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'name', 'file_name', 'type', 'size'];

    public function findById(int $id): ?Upload
    {
        return Upload::query()->with('media')->find($id);
    }

    public function findByUuid(string $uuid): ?Upload
    {
        return Upload::query()->with('media')->where('uuid', $uuid)->first();
    }

    public function buildFilterQuery(UploadFilterDTO $filters): Builder
    {
        $query = Upload::query();

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['name', 'file_name', 'uuid'],
            ['type' => $filters->type?->value],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    public function paginate(UploadFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters)->paginate($perPage);
    }

    public function create(array $data): Upload
    {
        return Upload::query()->create($data);
    }

    public function update(Upload $upload, array $data): Upload
    {
        $upload->update($data);

        return $upload->refresh();
    }

    public function attachMedia(Upload $upload, UploadedFile $file): Media
    {
        return $upload->addMedia($file)->toMediaCollection(MediaCollection::UPLOAD->value);
    }

    public function delete(Upload $upload): bool
    {
        return (bool) $upload->delete();
    }
}
