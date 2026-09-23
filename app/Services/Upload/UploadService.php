<?php

namespace App\Services\Upload;

use App\DTOs\Upload\UploadDTO;
use App\DTOs\Upload\UploadFilterDTO;
use App\Enums\MediaCollection;
use App\Enums\UploadType;
use App\Models\Upload;
use App\Repositories\Contracts\UploadRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UploadService
{
    public function __construct(
        protected UploadRepositoryInterface $repository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Upload>
     */
    public function paginate(UploadFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $filters->perPage);
    }

    public function find(int $id): ?Upload
    {
        return $this->repository->findById($id);
    }

    /**
     * Persist an uploaded file and attach it to the media library.
     */
    public function create(UploadDTO $dto): Upload
    {
        return DB::transaction(function () use ($dto): Upload {
            $file = $dto->file;
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';

            $upload = $this->repository->create([
                'uuid' => (string) Str::uuid(),
                'type' => UploadType::fromMimeType($mimeType),
                'name' => $file->getClientOriginalName(),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'created_by' => auth()->id(),
            ]);

            $media = $this->repository->attachMedia($upload, $file);

            return $this->repository->update($upload, [
                'media_id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ])->load('media');
        });
    }

    /**
     * Delete an upload and its media.
     */
    public function delete(Upload $upload): bool
    {
        return DB::transaction(function () use ($upload): bool {
            $upload->clearMediaCollection(MediaCollection::UPLOAD->value);

            return $this->repository->delete($upload);
        });
    }
}
