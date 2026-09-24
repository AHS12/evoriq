<?php

namespace App\Providers;

use App\Repositories\Contracts\CommandRunRepositoryInterface;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UploadRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\DataProcessingJob\DataProcessingJobRepository;
use App\Repositories\Developer\CommandRunRepository;
use App\Repositories\Notification\NotificationRepository;
use App\Repositories\Role\RoleRepository;
use App\Repositories\Upload\UploadRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * The repository interface to implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CommandRunRepositoryInterface::class => CommandRunRepository::class,
        DataProcessingJobRepositoryInterface::class => DataProcessingJobRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        UploadRepositoryInterface::class => UploadRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
    ];

    /**
     * Register the repository bindings.
     */
    public function register(): void
    {
        foreach ($this->bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
