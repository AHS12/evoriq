<?php

namespace App\Http\Controllers\Connection;

use App\DTOs\Connection\ConnectionDTO;
use App\DTOs\Connection\ConnectionFilterDTO;
use App\Enums\ApiRegion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connection\RotateConnectionKeyRequest;
use App\Http\Requests\Connection\SelectWorkspaceRequest;
use App\Http\Requests\Connection\StoreConnectionRequest;
use App\Http\Requests\Connection\UpdateConnectionRequest;
use App\Http\Requests\Connection\VerifyConnectionRequest;
use App\Http\Resources\Connection\ConnectionResource;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Connection\ClockifyConnectionService;
use App\Services\Connection\ConnectionVerifier;
use App\Services\Connection\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionVerifier $verifier,
        private readonly WorkspaceService $workspaces,
        private readonly ClockifyConnectionService $connections,
        private readonly ClockifyConnectionRepositoryInterface $repository,
    ) {}

    /**
     * The connections list + connect shell (CONN-04).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClockifyConnection::class);

        return Inertia::render('connections/index', [
            'connections' => ConnectionResource::collection(
                $this->repository->paginate(ConnectionFilterDTO::fromRequest($request)),
            ),
            'regions' => array_map(
                static fn (ApiRegion $region): array => [
                    'value' => $region->value,
                    'label' => $region->label(),
                ],
                ApiRegion::cases(),
            ),
            // MVP: only one connection is supported (the wizard has no picker).
            'canCreate' => Gate::allows('create', ClockifyConnection::class)
                && ! $this->repository->hasAny(),
        ]);
    }

    /**
     * Verify raw credentials for the connect preview (no persistence, CONN-02).
     */
    public function verify(VerifyConnectionRequest $request): JsonResponse
    {
        Gate::authorize('create', ClockifyConnection::class);

        $result = $this->verifier->inspect(
            (string) $request->validated('api_key'),
            $request->validated('addon_token') !== null ? (string) $request->validated('addon_token') : null,
            ApiRegion::from((string) $request->validated('region')),
        );

        return response()->json($result->toArray());
    }

    /**
     * Persist a verified connection with its chosen active workspace (CONN-04).
     */
    public function store(StoreConnectionRequest $request): RedirectResponse
    {
        Gate::authorize('create', ClockifyConnection::class);

        // MVP: a single Clockify connection is supported (no picker in the
        // import wizard yet), so reject any additional one.
        if ($this->repository->hasAny()) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Only one Clockify connection is supported for now.'),
            ]);

            return back();
        }

        $name = trim((string) ($request->validated('name') ?? ''));

        $connection = $this->connections->create(new ConnectionDTO(
            name: $name !== '' ? $name : 'Clockify',
            apiKey: (string) $request->validated('api_key'),
            addonToken: $request->validated('addon_token') !== null ? (string) $request->validated('addon_token') : null,
            region: ApiRegion::from((string) $request->validated('region')),
            subdomain: $request->validated('subdomain') !== null ? (string) $request->validated('subdomain') : null,
        ));

        $result = $this->verifier->verify($connection);

        if (! $result->ok) {
            $this->repository->delete($connection);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result->errorCode?->label() ?? __('The connection could not be verified.'),
            ]);

            return back();
        }

        try {
            $this->workspaces->selectActive($connection, (string) $request->validated('clockify_id'));
        } catch (RuntimeException) {
            // The verifier already marked the default workspace active.
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection added.')]);

        return to_route('connections.index');
    }

    /**
     * Rename a connection, optionally re-pointing it at a region/subdomain
     * (CONN-05).
     */
    public function update(UpdateConnectionRequest $request, ClockifyConnection $connection): RedirectResponse
    {
        Gate::authorize('update', $connection);

        $this->connections->rename(
            $connection,
            (string) $request->validated('name'),
            $request->validated('region') !== null ? ApiRegion::from((string) $request->validated('region')) : null,
            $request->validated('subdomain') !== null ? (string) $request->validated('subdomain') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection updated.')]);

        return to_route('connections.index');
    }

    /**
     * Re-verify a stored connection (CONN-02/CONN-05).
     */
    public function reverify(ClockifyConnection $connection): JsonResponse
    {
        Gate::authorize('reverify', $connection);

        return response()->json($this->connections->reverify($connection)->toArray());
    }

    /**
     * Rotate a connection's API key (CONN-05). The new key is verified before it
     * replaces the stored credential.
     */
    public function rotateKey(RotateConnectionKeyRequest $request, ClockifyConnection $connection): JsonResponse
    {
        Gate::authorize('rotateKey', $connection);

        $region = $request->validated('region') !== null
            ? ApiRegion::from((string) $request->validated('region'))
            : $connection->region;

        $result = $this->connections->rotateKey(
            $connection,
            (string) $request->validated('api_key'),
            $request->validated('addon_token') !== null
                ? (string) $request->validated('addon_token')
                : $connection->addon_token,
            $region,
            $request->validated('subdomain') !== null ? (string) $request->validated('subdomain') : null,
        );

        return response()->json($result->toArray());
    }

    /**
     * Disable a connection, stopping its scheduled and manual syncs (CONN-05).
     */
    public function disable(ClockifyConnection $connection): RedirectResponse
    {
        Gate::authorize('disable', $connection);

        $this->connections->disable($connection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection disabled.')]);

        return to_route('connections.index');
    }

    /**
     * Re-enable a disabled connection (CONN-05).
     */
    public function enable(ClockifyConnection $connection): RedirectResponse
    {
        Gate::authorize('enable', $connection);

        $this->connections->enable($connection);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection enabled.')]);

        return to_route('connections.index');
    }

    /**
     * Select the active workspace for a connection (CONN-03).
     */
    public function selectWorkspace(SelectWorkspaceRequest $request, ClockifyConnection $connection): JsonResponse
    {
        Gate::authorize('update', $connection);

        $workspace = $this->workspaces->selectActive(
            $connection,
            (string) $request->validated('clockify_id'),
        );

        return response()->json([
            'ok' => true,
            'workspace' => [
                'id' => $workspace->id,
                'clockify_id' => $workspace->clockify_id,
                'name' => $workspace->name,
                'active' => $workspace->active,
            ],
        ]);
    }
}
