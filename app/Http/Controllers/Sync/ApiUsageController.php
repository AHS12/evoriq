<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sync\ApiUsageResource;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Services\Sync\ApiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApiUsageController extends Controller
{
    public function __construct(
        private readonly ClockifyConnectionRepositoryInterface $connections,
        private readonly ClockifyWorkspaceRepositoryInterface $workspaces,
        private readonly ApiUsageService $usage,
    ) {}

    /**
     * The current API budget for the active connection (SYNC-02/SYNC-17).
     */
    public function show(): JsonResponse
    {
        Gate::authorize('viewAny', ClockifyConnection::class);

        $connection = $this->connections->findActive();

        if ($connection === null) {
            return response()->json(['api_usage' => null]);
        }

        $workspace = $connection->workspace_id !== null
            ? $this->workspaces->findByClockifyId($connection->id, $connection->workspace_id)
            : null;

        return response()->json([
            'api_usage' => ApiUsageResource::make(
                $this->usage->snapshot($connection, $workspace),
            )->resolve(),
        ]);
    }
}
