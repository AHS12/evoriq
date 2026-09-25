<?php

namespace App\Providers;

use App\Models\AuditActivity;
use App\Models\DataProcessingJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Upload;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\DataProcessingJobPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UploadPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        DataProcessingJob::class => DataProcessingJobPolicy::class,
        Upload::class => UploadPolicy::class,
        AuditActivity::class => AuditLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // The super admin bypasses every policy and gate check.
        Gate::before(fn (?User $user, string $ability): ?bool => $user?->isSuperAdmin() ? true : null);

        // Developer tooling is gated by the `developer.view` permission.
        Gate::define('viewPulse', fn (?User $user): bool => $user?->hasPermissionTo('developer.view') ?? false);
        Gate::define('viewDeveloperTools', fn (?User $user): bool => $user?->hasPermissionTo('developer.view') ?? false);

        // In-app maintenance actions are restricted to super admins.
        Gate::define('manageDeveloperTools', fn (?User $user): bool => $user?->isSuperAdmin() ?? false);
    }
}
