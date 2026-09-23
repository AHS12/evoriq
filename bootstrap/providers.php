<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\SetupServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

$providers = [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    FortifyServiceProvider::class,
    HealthServiceProvider::class,
    RepositoryServiceProvider::class,
    SetupServiceProvider::class,
    SettingsServiceProvider::class,
];

// Horizon requires ext-pcntl/ext-posix and therefore runs on Linux only.
if (extension_loaded('pcntl')) {
    $providers[] = HorizonServiceProvider::class;
}

// Telescope ships as a dev-only dependency; register it only when installed.
if (class_exists(TelescopeApplicationServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
