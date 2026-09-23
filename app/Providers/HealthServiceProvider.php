<?php

namespace App\Providers;

use App\Checks\DatabaseMigrationCheck;
use App\Enums\QueueName;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthServiceProvider extends ServiceProvider
{
    /**
     * Register the application health checks.
     */
    public function register(): void
    {
        $checks = [
            CacheCheck::new(),
            DatabaseCheck::new(),
            DatabaseConnectionCountCheck::new()
                ->warnWhenMoreConnectionsThan(50)
                ->failWhenMoreConnectionsThan(100),
            DatabaseMigrationCheck::new(),
            ScheduleCheck::new(),
        ];

        // `df` is unavailable on Windows, so the disk check would crash there.
        if (PHP_OS_FAMILY !== 'Windows') {
            $checks[] = UsedDiskSpaceCheck::new();
        }

        if ($this->app->environment('local', 'testing')) {
            // Local development runs a Redis queue worker (Horizon needs ext-pcntl).
            $checks[] = QueueCheck::new()->onQueue([
                QueueName::CRITICAL->value,
                QueueName::DEFAULT->value,
                QueueName::HEAVY->value,
            ]);
        } else {
            $checks[] = EnvironmentCheck::new();
            $checks[] = DebugModeCheck::new();
            $checks[] = OptimizedAppCheck::new();
            $checks[] = HorizonCheck::new();
            $checks[] = RedisCheck::new();
        }

        Health::checks($checks);
    }
}
