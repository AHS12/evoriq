<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Developer\CommandRunResource;
use App\Services\Developer\CommandRunService;
use App\Services\Developer\DeveloperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Laravel\Telescope\Telescope;
use Spatie\Health\Checks\Check;
use Spatie\Health\Facades\Health;
use Throwable;

class DeveloperController extends Controller
{
    public function __construct(
        private readonly DeveloperService $developer,
        private readonly CommandRunService $commandRuns,
    ) {}

    /**
     * Show the in-app developer tools dashboard.
     */
    public function index(): Response
    {
        Gate::authorize('viewDeveloperTools');

        return Inertia::render('admin/settings/developer', [
            'tools' => $this->tools(),
            'health' => $this->health(),
            'system' => $this->developer->system(),
            'maintenance' => [
                'enabled' => $this->developer->maintenanceEnabled(),
                'mode' => $this->developer->maintenanceMode(),
                'actions' => $this->developer->maintenanceActions(),
                'runs' => CommandRunResource::collection($this->commandRuns->recent())->resolve(),
            ],
        ]);
    }

    /**
     * Queue a gated maintenance action.
     */
    public function maintenance(Request $request, string $action): RedirectResponse
    {
        Gate::authorize('manageDeveloperTools');

        abort_unless($this->developer->maintenanceEnabled(), 404);

        try {
            $run = $this->commandRuns->dispatch($action);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        Log::info('Developer maintenance action queued.', [
            'action' => $action,
            'command_run_id' => $run->id,
            'user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Maintenance action queued.'),
        ]);

        return back();
    }

    /**
     * Toggle the application maintenance mode.
     */
    public function maintenanceMode(Request $request): RedirectResponse
    {
        Gate::authorize('manageDeveloperTools');

        abort_unless($this->developer->maintenanceEnabled(), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];

        $this->developer->setMaintenanceMode($enabled);

        Log::info('Developer maintenance mode toggled.', [
            'enabled' => $enabled,
            'user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? __('Application is now in maintenance mode.')
                : __('Application is live again.'),
        ]);

        return back();
    }

    /**
     * The developer tools available to the current environment.
     *
     * @return list<array{key: string, title: string, description: string, href: string, available: bool}>
     */
    private function tools(): array
    {
        return [
            [
                'key' => 'telescope',
                'title' => 'Telescope',
                'description' => 'Inspect requests, exceptions, queries and jobs.',
                'href' => '/telescope',
                'available' => class_exists(Telescope::class) && (bool) config('telescope.enabled'),
            ],
            [
                'key' => 'pulse',
                'title' => 'Pulse',
                'description' => 'Application performance and usage metrics.',
                'href' => '/pulse',
                'available' => (bool) config('pulse.enabled'),
            ],
            [
                'key' => 'horizon',
                'title' => 'Horizon',
                'description' => 'Queue supervision and monitoring (Linux only).',
                'href' => '/horizon',
                'available' => extension_loaded('pcntl'),
            ],
        ];
    }

    /**
     * Run the registered health checks and summarise their results.
     *
     * @return array<int, array{name: string, status: string, summary: string}>
     */
    private function health(): array
    {
        return Health::registeredChecks()
            ->map(function (Check $check): array {
                try {
                    $result = $check->run();

                    return [
                        'name' => $check->getName(),
                        'status' => (string) $result->status->value,
                        'summary' => $result->getShortSummary(),
                    ];
                } catch (Throwable $e) {
                    return [
                        'name' => $check->getName(),
                        'status' => 'crashed',
                        'summary' => $e->getMessage(),
                    ];
                }
            })
            ->values()
            ->all();
    }
}
