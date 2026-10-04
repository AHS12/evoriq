<?php

namespace App\Http\Controllers\Sync;

use App\DTOs\Sync\PlanRequest;
use App\Enums\ApiRegion;
use App\Enums\SyncEntityType;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use App\Exceptions\SyncBudgetExhausted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\InspectImportRequest;
use App\Http\Requests\Sync\StoreImportRequest;
use App\Http\Resources\Connection\ConnectionResource;
use App\Http\Resources\Pipeline\PipelineEventResource;
use App\Http\Resources\Sync\ImportPlanResource;
use App\Models\ClockifySyncRun;
use App\Services\Sync\ImportWizardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The historical import wizard (PIPE-09) wired to the planner (SYNC-03) and the
 * orchestrator (SYNC-09) with the real entity set (ENT-14).
 */
class ImportWizardController extends Controller
{
    public function __construct(
        private readonly ImportWizardService $service,
    ) {}

    /**
     * The wizard shell: connected workspace (or connect prompt), range options
     * and any active import to resume.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewImportWizard');

        $connection = $this->service->connection();
        $workspace = $connection !== null ? $this->service->workspace($connection) : null;
        $active = $this->service->activeRun();

        return Inertia::render('import/index', [
            'connection' => $connection !== null
                ? ConnectionResource::make($connection)->resolve($request)
                : null,
            'workspace' => $workspace === null ? null : [
                'clockify_id' => $workspace->clockify_id,
                'name' => $workspace->name,
                'currency' => $workspace->currency,
                'time_zone' => $workspace->time_zone,
            ],
            'activeImport' => $active !== null ? $this->service->present($active) : null,
            'maxHistoryYears' => $this->service->maxHistoryYears(),
            'regions' => array_map(
                static fn (ApiRegion $region): array => [
                    'value' => $region->value,
                    'label' => $region->label(),
                ],
                ApiRegion::cases(),
            ),
        ]);
    }

    /**
     * Plan a historical import for the active connection (no writes).
     */
    public function inspect(InspectImportRequest $request): JsonResponse
    {
        Gate::authorize('triggerSync');

        try {
            $plan = $this->service->plan($this->planRequest(
                $request,
                $request->validated('range_start'),
                $request->validated('range_end'),
            ));
        } catch (SyncBudgetExhausted $exception) {
            return response()->json([
                'message' => __('The Clockify API limit was reached while inspecting the workspace. You can start the import without an estimate.'),
                'resets_in' => $exception->resetsIn,
            ], 429);
        }

        return response()->json(ImportPlanResource::make($plan)->resolve());
    }

    /**
     * Start the umbrella import run, or hand back the active one (concurrency
     * guard). Entity reference dimensions load before facts in plan order.
     */
    public function store(StoreImportRequest $request): RedirectResponse
    {
        Gate::authorize('triggerSync');

        $connection = $this->service->connection();

        if ($connection === null) {
            throw ValidationException::withMessages([
                'connection' => __('No active Clockify connection.'),
            ]);
        }

        if ($this->service->workspace($connection) === null) {
            throw ValidationException::withMessages([
                'workspace' => __('No active workspace.'),
            ]);
        }

        $active = $this->service->activeRun();

        if ($active !== null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('An import is already running — returning to it.'),
            ]);

            return to_route('import.show', $active);
        }

        $planRequest = $this->planRequest(
            $request,
            $request->rangeStart(),
            $request->rangeEnd(),
        );

        try {
            $plan = $this->service->plan($planRequest);
        } catch (SyncBudgetExhausted $exception) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('The Clockify API limit was reached — try again in :minutes minutes.', [
                    'minutes' => max(1, (int) ceil($exception->resetsIn / 60)),
                ]),
            ]);

            return back();
        }

        $run = $this->service->start($planRequest, $plan);

        return to_route('import.show', $run);
    }

    /**
     * The live run view (PIPE-05), with incremental event windows.
     */
    public function show(Request $request, ClockifySyncRun $syncRun): Response
    {
        Gate::authorize('viewImportWizard');

        $window = $this->service->eventWindow(
            $syncRun,
            $this->nullableInt($request->query('after_sequence')),
            $this->nullableInt($request->query('before_sequence')),
        );

        return Inertia::render('import/show', [
            'run' => $this->service->present($syncRun),
            'events' => PipelineEventResource::collection($window->events)->toArray($request),
            'eventsMeta' => $window->meta(),
        ]);
    }

    private function planRequest(InspectImportRequest|StoreImportRequest $request, mixed $start, mixed $end): PlanRequest
    {
        $connection = $this->service->connection();

        if ($connection === null) {
            throw ValidationException::withMessages([
                'connection' => __('No active Clockify connection.'),
            ]);
        }

        $workspace = $this->service->workspace($connection);

        if ($workspace === null) {
            throw ValidationException::withMessages([
                'workspace' => __('No active workspace.'),
            ]);
        }

        return new PlanRequest(
            connection: $connection,
            workspace: $workspace,
            rangeStart: CarbonImmutable::parse((string) $start),
            rangeEnd: CarbonImmutable::parse((string) $end),
            mode: $this->mode($request),
            priority: $this->priority($request),
            entities: $this->entities($request),
            pageSize: (int) config('clockify.pagination.page_size', 200),
        );
    }

    private function mode(InspectImportRequest|StoreImportRequest $request): SyncMode
    {
        $mode = $request->validated('mode');

        return is_string($mode) ? SyncMode::from($mode) : SyncMode::INITIAL;
    }

    private function priority(InspectImportRequest|StoreImportRequest $request): SyncPriority
    {
        $priority = $request->validated('priority');

        return is_string($priority) ? SyncPriority::from($priority) : SyncPriority::NORMAL;
    }

    /**
     * @return array<int, SyncEntityType>|null
     */
    private function entities(InspectImportRequest|StoreImportRequest $request): ?array
    {
        $entities = $request->validated('entities');

        if (! is_array($entities) || $entities === []) {
            return null;
        }

        return array_values(array_map(
            static fn (string $entity): SyncEntityType => SyncEntityType::from($entity),
            array_filter($entities, static fn (mixed $entity): bool => is_string($entity)),
        ));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
