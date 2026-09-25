<?php

namespace App\Providers;

use App\Listeners\Audit\AuthAuditSubscriber;
use App\Services\Clockify\ClockifyClient;
use App\Services\Clockify\ClockifyPaginator;
use App\Services\Clockify\ClockifyRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerClockifyServices();
    }

    /**
     * Register the Clockify integration boundary.
     */
    protected function registerClockifyServices(): void
    {
        $this->app->singleton(ClockifyRateLimiter::class, fn (): ClockifyRateLimiter => new ClockifyRateLimiter(
            key: 'api',
            requestsPerSecond: config('clockify.rate_limit.requests_per_second'),
            burstLimit: config('clockify.rate_limit.burst_limit'),
            cooldownSeconds: config('clockify.rate_limit.cooldown'),
        ));

        $this->app->singleton(ClockifyPaginator::class, fn (): ClockifyPaginator => new ClockifyPaginator(
            pageSize: config('clockify.pagination.page_size'),
            maxPageSize: config('clockify.pagination.max_page_size'),
        ));

        $this->app->singleton(ClockifyClient::class, fn ($app): ClockifyClient => new ClockifyClient(
            $app->make(ClockifyRateLimiter::class),
            $app->make(ClockifyPaginator::class),
            config('clockify'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
        $this->configureAudit();
    }

    /**
     * Record authentication and account-security events on the audit trail.
     */
    protected function configureAudit(): void
    {
        Event::subscribe(AuthAuditSubscriber::class);
    }

    /**
     * Make `composer run dev` consume every queue channel locally.
     */
    protected function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::artisan(
            'queue:listen --queue=critical,default,heavy --tries=1 --timeout=0',
            'queue',
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
