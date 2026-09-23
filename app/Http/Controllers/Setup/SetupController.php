<?php

namespace App\Http\Controllers\Setup;

use App\DTOs\Setup\DatabaseConfigDTO;
use App\DTOs\Setup\DriverConfigDTO;
use App\DTOs\Setup\SetupDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreDatabaseRequest;
use App\Http\Requests\Setup\StoreDriversRequest;
use App\Http\Requests\Setup\StoreSuperAdminRequest;
use App\Services\Setup\DatabaseConfigurator;
use App\Services\Setup\DriverConfigurator;
use App\Services\Setup\SetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(
        protected SetupService $setup,
        protected DatabaseConfigurator $databases,
        protected DriverConfigurator $drivers,
    ) {}

    /**
     * Show the first-run setup wizard.
     */
    public function show(): Response
    {
        $admin = $this->setup->existingSuperAdmin();

        return Inertia::render('setup/index', [
            'requirements' => $this->setup->requirements(),
            'status' => $this->setup->status(),
            'hasSuperAdmin' => $admin !== null,
            'existingAdmin' => $admin === null ? null : [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'defaults' => [
                'appName' => $this->setup->defaultAppName(),
            ],
            'database' => [
                'drivers' => $this->databases->drivers(),
                'current' => $this->databases->current(),
            ],
            'drivers' => [
                'options' => $this->drivers->options(),
                'current' => $this->drivers->current(),
                'redis' => $this->drivers->redis(),
                'recommended' => $this->drivers->recommended(),
            ],
        ]);
    }

    /**
     * Generate the application key and prepare the environment file.
     */
    public function generateKey(): RedirectResponse
    {
        $this->setup->generateAppKey();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Application key generated.'),
        ]);

        return back();
    }

    /**
     * Probe the supplied database credentials without saving them.
     */
    public function testDatabase(StoreDatabaseRequest $request): RedirectResponse
    {
        $result = $this->databases->test(DatabaseConfigDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }

    /**
     * Persist the database configuration.
     */
    public function storeDatabase(StoreDatabaseRequest $request): RedirectResponse
    {
        $dto = DatabaseConfigDTO::fromRequest($request);
        $result = $this->databases->provision($dto);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['database' => $result['message']]);
        }

        $this->databases->write($dto);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['message'],
        ]);

        return back();
    }

    /**
     * Persist the session, cache and queue drivers.
     */
    public function storeDrivers(StoreDriversRequest $request): RedirectResponse
    {
        $this->drivers->write(DriverConfigDTO::fromRequest($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Runtime drivers saved.'),
        ]);

        return back();
    }

    /**
     * Run migrations and the required seeders, then link storage.
     */
    public function migrate(): RedirectResponse
    {
        $result = $this->setup->migrate();

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'migrate' => $result['output'] !== '' ? $result['output'] : __('Migration failed.'),
            ]);
        }

        $this->setup->storageLink();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Database migrated and seeded.'),
        ]);

        return back();
    }

    /**
     * Create the super admin and complete setup.
     */
    public function store(StoreSuperAdminRequest $request): RedirectResponse
    {
        if (! $this->setup->seeded()) {
            throw ValidationException::withMessages([
                'database' => __('Run the database migrations before finishing setup.'),
            ]);
        }

        $user = $this->setup->complete(SetupDTO::fromRequest($request));

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Setup complete. Welcome to Evoriq!'),
        ]);

        return to_route('dashboard');
    }
}
