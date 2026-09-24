<?php

namespace App\Http\Controllers\File;

use App\DTOs\Upload\UploadDTO;
use App\Enums\UploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\StoreUploadRequest;
use App\Http\Resources\File\FileEntryResource;
use App\Models\DataProcessingJob;
use App\Models\Upload;
use App\Models\User;
use App\Services\File\FileBrowserService;
use App\Services\Upload\UploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly FileBrowserService $browser,
    ) {}

    /**
     * Browse all files (uploads + generated artifacts) or generated only.
     */
    public function index(Request $request): Response
    {
        $source = match ($request->string('source')->toString()) {
            'generated', 'reports' => 'generated',
            default => 'all',
        };

        $canViewGenerated = $this->canViewGenerated($request);

        if ($source === 'generated') {
            Gate::authorize('viewAny', DataProcessingJob::class);
        } else {
            Gate::authorize('viewAny', Upload::class);
        }

        $type = $request->filled('type')
            ? UploadType::tryFrom((string) $request->input('type'))
            : null;

        $files = $this->browser->paginate(
            search: $request->string('search')->toString() ?: null,
            type: $type,
            source: $source,
            generatedUserId: $this->canViewAll($request) ? null : (int) $request->user()->getKey(),
            includeGenerated: $canViewGenerated,
        )->withQueryString();

        return Inertia::render('files/index', [
            'source' => $source,
            'files' => FileEntryResource::collection($files),
            'filters' => [
                'search' => $request->input('search'),
                'type' => $request->input('type'),
            ],
            'types' => array_map(
                fn (UploadType $type): array => ['value' => $type->value, 'label' => $type->label()],
                UploadType::cases(),
            ),
            'canViewGenerated' => $canViewGenerated,
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

    /**
     * Whether the user can see system-generated files at all.
     */
    private function canViewGenerated(Request $request): bool
    {
        $permissions = $this->permissions($request);

        return $permissions->contains('data-processing.view')
            || $permissions->contains('data-processing.view.all');
    }

    /**
     * Whether the user can see every user's generated files.
     */
    private function canViewAll(Request $request): bool
    {
        return $this->permissions($request)->contains('data-processing.view.all');
    }

    /**
     * @return Collection<int, string>
     */
    private function permissions(Request $request): Collection
    {
        $user = $request->user();

        return $user instanceof User
            ? $user->getAllPermissions()->pluck('name')
            : collect();
    }
}
