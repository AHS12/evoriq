<?php

use App\Services\Developer\DeveloperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DeveloperService;
});

test('exposes the maintenance actions', function () {
    $actions = $this->service->maintenanceActions();

    expect($actions)->toHaveCount(7)
        ->and(array_column($actions, 'key'))
        ->toContain('cache-clear', 'config-clear', 'route-clear', 'view-clear', 'settings-sync', 'permission-sync', 'setup-reset');
});

test('maintenanceEnabled reflects the config flag', function () {
    config(['developer.maintenance_actions' => true]);
    expect($this->service->maintenanceEnabled())->toBeTrue();

    config(['developer.maintenance_actions' => false]);
    expect($this->service->maintenanceEnabled())->toBeFalse();
});

test('setMaintenanceMode toggles the application state', function () {
    try {
        $this->service->setMaintenanceMode(true);
        expect($this->service->maintenanceMode())->toBeTrue();

        $this->service->setMaintenanceMode(false);
        expect($this->service->maintenanceMode())->toBeFalse();
    } finally {
        Artisan::call('up');
    }
});

test('system reports the runtime details', function () {
    $system = $this->service->system();

    expect($system)
        ->toHaveKeys(['environment', 'php_version', 'laravel_version', 'timezone', 'debug', 'config_cached', 'routes_cached', 'events_cached'])
        ->and($system['environment'])->toBe('testing');
});
