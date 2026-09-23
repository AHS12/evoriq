<?php

namespace App\Services\Developer;

use App\Registry\MaintenanceActionRegistry;
use Illuminate\Support\Facades\Artisan;

class DeveloperService
{
    /**
     * Whether in-app maintenance actions are enabled.
     */
    public function maintenanceEnabled(): bool
    {
        return (bool) config('developer.maintenance_actions', false);
    }

    /**
     * The maintenance actions available to the UI.
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function maintenanceActions(): array
    {
        return MaintenanceActionRegistry::actions();
    }

    /**
     * Whether the application is currently in maintenance mode.
     */
    public function maintenanceMode(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * Put the application into, or out of, maintenance mode.
     */
    public function setMaintenanceMode(bool $enabled): void
    {
        Artisan::call($enabled ? 'down' : 'up');
    }

    /**
     * Runtime and cache status information.
     *
     * @return array<string, string|bool>
     */
    public function system(): array
    {
        return [
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timezone' => (string) config('app.timezone'),
            'debug' => (bool) config('app.debug'),
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'events_cached' => app()->eventsAreCached(),
        ];
    }
}
