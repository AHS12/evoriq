<?php

namespace App\Http\Controllers\DataProcessingJob;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\DTOs\DataProcessingJob\JobRequest;
use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;
use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataProcessingJob\StoreImportRequest;
use App\Http\Requests\Export\StoreExportRequest;
use App\Http\Resources\Export\DataProcessingJobResource;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Support\DataProcessingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityController extends Controller
{
    public function __construct(
        private readonly DataProcessingJobService $service,
    ) {}

    /**
     * Show the Data Processing Center (Job Center).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DataProcessingJob::class);

        $user = $request->user();
        $viewAll = $this->canViewAll($request);
        $filters = DataProcessingJobFilterDTO::fromRequest($request);

        if (! $viewAll) {
            $filters = $filters->scopedToUser((int) $user->getKey());
        }

        return Inertia::render('data-processing/index', [
            'jobs' => DataProcessingJobResource::collection(
                $this->service->paginate($filters)->withQueryString(),
            ),
            'stats' => $this->service->statsFor((int) $user->getKey(), $viewAll),
            'activeJobs' => $this->service->activeCountFor((int) $user->getKey(), $viewAll),
            'filters' => [
                'search' => $filters->search,
                'type' => $filters->type?->value,
                'status' => $filters->status?->value,
                'entity_type' => $filters->entityType?->value,
                'order_by' => $filters->orderBy,
                'order_direction' => $filters->orderDirection,
                'per_page' => $filters->perPage,
            ],
            'options' => DataProcessingOptions::make(),
        ]);
    }

    /**
     * Queue an export.
     */
    public function storeExport(StoreExportRequest $request): RedirectResponse
    {
        $dto = DataProcessingJobDTO::fromRequest($request);

        Gate::authorize('create', [DataProcessingJob::class, DataProcessingJobType::EXPORT, $dto->entityType]);

        return $this->queued($this->service->createExport($dto));
    }

    /**
     * Queue an import from an uploaded file.
     */
    public function storeImport(StoreImportRequest $request): RedirectResponse
    {
        $entity = DataEntity::from((string) $request->validated('entity_type'));

        Gate::authorize('create', [DataProcessingJob::class, DataProcessingJobType::IMPORT, $entity]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $disk = (string) config('exports.disk', 'local');
        $basePath = trim((string) config('exports.base_path', 'exports'), '/');
        $path = $file->store("{$basePath}/imports", $disk);

        if ($path === false) {
            throw new RuntimeException(__('The uploaded file could not be stored.'));
        }

        $job = $this->service->dispatch(new JobRequest(
            entity: $entity,
            type: DataProcessingJobType::IMPORT,
            format: $this->formatFor($file->getClientOriginalExtension()),
            parameters: [
                'send_invitations' => $request->boolean('filters.send_invitations', true),
                'default_role' => $request->input('filters.default_role'),
            ],
            inputDisk: $disk,
            inputPath: $path,
            originalFileName: $file->getClientOriginalName(),
        ));

        return $this->queued($job);
    }

    /**
     * Download the import template for an entity.
     */
    public function template(Request $request, string $entity): BinaryFileResponse
    {
        $dataEntity = DataEntity::tryFrom($entity) ?? abort(404);

        Gate::authorize('create', [DataProcessingJob::class, DataProcessingJobType::IMPORT, $dataEntity]);

        $format = ExportFormat::tryFrom((string) $request->query('format', ExportFormat::CSV->value))
            ?? ExportFormat::CSV;

        return Excel::download(
            new ImportTemplateExport($dataEntity),
            "{$dataEntity->value}_import_template.{$format->extension()}",
        );
    }

    /**
     * Cancel a job.
     */
    public function cancel(DataProcessingJob $dataProcessingJob): RedirectResponse
    {
        Gate::authorize('manage', $dataProcessingJob);

        $this->service->cancel($dataProcessingJob);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job cancelled.')]);

        return back();
    }

    /**
     * Retry a finished job.
     */
    public function retry(DataProcessingJob $dataProcessingJob): RedirectResponse
    {
        Gate::authorize('manage', $dataProcessingJob);

        $this->service->retry($dataProcessingJob);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job queued again.')]);

        return back();
    }

    /**
     * Queue a fresh copy of a job.
     */
    public function duplicate(DataProcessingJob $dataProcessingJob): RedirectResponse
    {
        Gate::authorize('manage', $dataProcessingJob);

        return $this->queued($this->service->duplicate($dataProcessingJob));
    }

    /**
     * Delete a job and its files.
     */
    public function destroy(DataProcessingJob $dataProcessingJob): RedirectResponse
    {
        Gate::authorize('delete', $dataProcessingJob);

        $this->service->delete($dataProcessingJob);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job deleted.')]);

        return back();
    }

    /**
     * Flash the queue confirmation and return to the list.
     */
    private function queued(DataProcessingJob $job): RedirectResponse
    {
        Inertia::flash('job_queued', [
            'name' => $job->displayName(),
            'job_id' => $job->job_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name queued.', ['name' => $job->displayName()]),
        ]);

        return back();
    }

    private function formatFor(string $extension): ExportFormat
    {
        return strtolower($extension) === 'xlsx' ? ExportFormat::XLSX : ExportFormat::CSV;
    }

    /**
     * Whether the user may see every user's jobs.
     */
    private function canViewAll(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $user->getAllPermissions()->pluck('name')->contains('data-processing.view.all');
    }
}
