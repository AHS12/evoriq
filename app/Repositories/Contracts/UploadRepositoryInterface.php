<?php

namespace App\Repositories\Contracts;

use App\DTOs\Upload\UploadFilterDTO;
use App\Models\Upload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

interface UploadRepositoryInterface
{
    public function findById(int $id): ?Upload;

    public function findByUuid(string $uuid): ?Upload;

    /**
     * @return Builder<Upload>
     */
    public function buildFilterQuery(UploadFilterDTO $filters): Builder;

    /**
     * @return LengthAwarePaginator<int, Upload>
     */
    public function paginate(UploadFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Upload;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Upload $upload, array $data): Upload;

    public function attachMedia(Upload $upload, UploadedFile $file): Media;

    public function delete(Upload $upload): bool;
}
