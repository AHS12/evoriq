<?php

namespace App\Http\Controllers\File;

use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\DTOs\Upload\UploadDTO;
use App\DTOs\Upload\UploadFilterDTO;
use App\Enums\UploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\StoreUploadRequest;
use App\Http\Resources\Export\DataProcessingJobResource;
use App\Http\Resources\Upload\UploadResource;
use App\Models\DataProcessingJob;
use App\Models\Upload;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Upload\UploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly DataProcessingJobService $jobs,
    ) {}

    /**
     * Browse user files or generated report files.
     */
    public function index(Request $request): Response
    {
        $source = $request->string('source')->toString() === 'reports'
            ? 'reports'
            : 'files';

        if ($source === 'reports') {
            Gate::authorize('viewAny', DataProcessingJob::class);

            return Inertia::render('files/index', [
                'source' => 'reports',
                'files' => null,
                'reports' => DataProcessingJobResource::collection(
                    $this->jobs->paginate(DataProcessingJobFilterDTO::fromRequest($request)),
                ),
                'filters' => [
                    'search' => $request->input('search'),
                ],
            ]);
        }

        Gate::authorize('viewAny', Upload::class);

        return Inertia::render('files/index', [
            'source' => 'files',
            'files' => UploadResource::collection(
                $this->uploads->paginate(UploadFilterDTO::fromRequest($request)),
            ),
            'reports' => null,
            'filters' => [
                'search' => $request->input('search'),
                'type' => $request->input('type'),
            ],
            'types' => array_map(
                fn (UploadType $type): array => ['value' => $type->value, 'label' => $type->label()],
                UploadType::cases(),
            ),
        ]);
    }

    /**
     * Store a newly uploaded file.
     */
    public function store(StoreUploadRequest $request): RedirectResponse
    {
        Gate::authorize('create', Upload::class);

        $this->uploads->create(UploadDTO::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File uploaded.')]);

        return to_route('files.index');
    }

    /**
     * Delete a file and its media.
     */
    public function destroy(Upload $upload): RedirectResponse
    {
        Gate::authorize('delete', $upload);

        $this->uploads->delete($upload);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return to_route('files.index');
    }
}
