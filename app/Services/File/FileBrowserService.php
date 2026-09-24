<?php

namespace App\Services\File;

use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\MediaCollection;
use App\Enums\UploadType;
use App\Models\Upload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a unified, paginated view over user uploads and system-generated
 * artifacts (completed data processing jobs with a downloadable file).
 */
class FileBrowserService
{
    /**
     * @param  'all'|'upload'|'generated'  $source
     * @param  int|null  $generatedUserId  Restrict generated files to a user (null = all).
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        ?string $search,
        ?UploadType $type,
        string $source = 'all',
        ?int $generatedUserId = null,
        bool $includeGenerated = true,
        int $perPage = 24,
    ): LengthAwarePaginator {
        $parts = [];

        if ($source !== 'generated') {
            $parts[] = $this->uploadsQuery($search, $type);
        }

        if ($source !== 'upload' && $includeGenerated) {
            $parts[] = $this->generatedQuery($search, $type, $generatedUserId);
        }

        if ($parts === []) {
            $parts[] = $this->uploadsQuery($search, $type);
        }

        $union = $parts[0];

        foreach (array_slice($parts, 1) as $part) {
            $union = $union->unionAll($part);
        }

        $paginator = DB::query()
            ->fromSub($union, 'files')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $uploadIds = collect($paginator->items())
            ->map(fn (mixed $row): array => (array) $row)
            ->where('source', 'upload')
            ->pluck('id')
            ->all();

        $uploads = $uploadIds === []
            ? collect()
            : Upload::query()->with('media')->whereIn('id', $uploadIds)->get()->keyBy('id');

        $paginator->setCollection(
            collect($paginator->items())
                ->map(fn (mixed $row): array => $this->normalize((array) $row, $uploads)),
        );

        return $paginator;
    }

    private function uploadsQuery(?string $search, ?UploadType $type): Builder
    {
        $query = DB::table('uploads')->select([
            'id',
            DB::raw("'upload' as source"),
            'name',
            'file_name',
            'mime_type',
            'size',
            'url',
            'type',
            DB::raw('null as job_id'),
            DB::raw('null as job_type'),
            'created_at',
        ]);

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query;
    }

    private function generatedQuery(?string $search, ?UploadType $type, ?int $generatedUserId): Builder
    {
        $query = DB::table('data_processing_jobs')->select([
            'id',
            DB::raw("'generated' as source"),
            'file_name as name',
            'file_name',
            'mime_type',
            DB::raw('file_size as size'),
            DB::raw('null as url'),
            DB::raw("'artifact' as type"),
            'job_id',
            'type as job_type',
            'created_at',
        ])
            ->where('status', DataProcessingJobStatus::COMPLETED->value)
            ->whereNotNull('file_path')
            ->whereNotNull('file_name');

        if ($generatedUserId !== null) {
            $query->where('user_id', $generatedUserId);
        }

        if ($search !== null && $search !== '') {
            $query->where('file_name', 'like', "%{$search}%");
        }

        // Upload-type filters never match generated artifacts.
        if ($type !== null) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  Collection<int, Upload>  $uploads
     * @return array<string, mixed>
     */
    private function normalize(array $row, Collection $uploads): array
    {
        if (($row['source'] ?? null) === 'generated') {
            $jobType = ($row['job_type'] ?? null) !== null
                ? DataProcessingJobType::tryFrom((string) $row['job_type'])
                : null;

            return [
                'id' => (int) $row['id'],
                'source' => 'generated',
                'is_generated' => true,
                'name' => $row['name'],
                'file_name' => $row['file_name'],
                'mime_type' => $row['mime_type'],
                'size' => $row['size'] !== null ? (int) $row['size'] : null,
                'type' => 'artifact',
                'type_label' => 'Generated',
                'url' => null,
                'thumb_url' => null,
                'download_url' => route('exports.download', (int) $row['id']),
                'job_id' => $row['job_id'],
                'job_type' => $row['job_type'],
                'job_type_label' => $jobType?->label(),
                'created_at' => $row['created_at'],
            ];
        }

        /** @var Upload|null $upload */
        $upload = $uploads->get((int) $row['id']);

        return [
            'id' => (int) $row['id'],
            'source' => 'upload',
            'is_generated' => false,
            'name' => $row['name'],
            'file_name' => $row['file_name'],
            'mime_type' => $row['mime_type'],
            'size' => $row['size'] !== null ? (int) $row['size'] : null,
            'type' => $row['type'],
            'type_label' => UploadType::tryFrom((string) $row['type'])?->label() ?? 'File',
            'url' => $row['url'],
            'thumb_url' => $upload?->getFirstMediaUrl(MediaCollection::UPLOAD->value, 'thumb') ?: null,
            'download_url' => $row['url'],
            'job_id' => null,
            'job_type' => null,
            'job_type_label' => null,
            'created_at' => $row['created_at'],
        ];
    }
}
