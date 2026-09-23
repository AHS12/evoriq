<?php

namespace App\Http\Controllers\Export;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\StoreExportRequest;
use App\Http\Resources\Export\DataProcessingJobResource;
use App\Models\DataProcessingJob;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly DataProcessingJobService $service,
    ) {}

    /**
     * List data processing jobs.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DataProcessingJob::class);

        $jobs = $this->service->paginate(DataProcessingJobFilterDTO::fromRequest($request));

        return $this->respond(DataProcessingJobResource::collection($jobs));
    }

    /**
     * Queue a new export job.
     */
    public function store(StoreExportRequest $request): JsonResponse
    {
        Gate::authorize('create', DataProcessingJob::class);

        $job = $this->service->createExport(DataProcessingJobDTO::fromRequest($request));

        return $this->respond(DataProcessingJobResource::make($job), Response::HTTP_CREATED);
    }

    /**
     * Show a single data processing job.
     */
    public function show(DataProcessingJob $dataProcessingJob): JsonResponse
    {
        Gate::authorize('view', $dataProcessingJob);

        return $this->respond(DataProcessingJobResource::make($dataProcessingJob));
    }

    /**
     * Download the generated file for a completed job.
     */
    public function download(DataProcessingJob $dataProcessingJob): StreamedResponse
    {
        Gate::authorize('download', $dataProcessingJob);

        $file = $this->service->resolveDownload($dataProcessingJob);

        if ($file === null) {
            $this->fail(
                ApiErrorCode::FILE_NOT_FOUND->value,
                'The export file is not available.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return Storage::disk($file['disk'])->download($file['path'], $file['download_name']);
    }

    /**
     * Delete a data processing job and its file.
     */
    public function destroy(DataProcessingJob $dataProcessingJob): JsonResponse
    {
        Gate::authorize('delete', $dataProcessingJob);

        $this->service->delete($dataProcessingJob);

        return $this->successNoContent();
    }
}
