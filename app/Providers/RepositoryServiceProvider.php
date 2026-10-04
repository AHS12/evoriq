<?php

namespace App\Providers;

use App\Repositories\AuditLog\AuditLogRepository;
use App\Repositories\Backup\BackupRunRepository;
use App\Repositories\Connection\ClockifyConnectionRepository;
use App\Repositories\Connection\ClockifyWorkspaceRepository;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Repositories\Contracts\ClockifyApiUsageRepositoryInterface;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifyDeletedEntityRepositoryInterface;
use App\Repositories\Contracts\ClockifyEntityChangeRepositoryInterface;
use App\Repositories\Contracts\ClockifyMembershipRepositoryInterface;
use App\Repositories\Contracts\ClockifyProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ClockifyProjectRepositoryInterface;
use App\Repositories\Contracts\ClockifyRawRecordRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Repositories\Contracts\CommandRunRepositoryInterface;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TimeEntryTagRepositoryInterface;
use App\Repositories\Contracts\UploadRepositoryInterface;
use App\Repositories\Contracts\UserCfValueRepositoryInterface;
use App\Repositories\Contracts\UserGroupMemberRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\DataProcessingJob\DataProcessingJobRepository;
use App\Repositories\Developer\CommandRunRepository;
use App\Repositories\Entity\ClockifyMembershipRepository;
use App\Repositories\Entity\ClockifyProjectMemberRepository;
use App\Repositories\Entity\ClockifyProjectRepository;
use App\Repositories\Entity\TimeEntryTagRepository;
use App\Repositories\Entity\UserCfValueRepository;
use App\Repositories\Entity\UserGroupMemberRepository;
use App\Repositories\Notification\NotificationRepository;
use App\Repositories\Pipeline\PipelineEventRepository;
use App\Repositories\Role\RoleRepository;
use App\Repositories\Sync\ClockifyApiUsageRepository;
use App\Repositories\Sync\ClockifyDeletedEntityRepository;
use App\Repositories\Sync\ClockifyEntityChangeRepository;
use App\Repositories\Sync\ClockifyRawRecordRepository;
use App\Repositories\Sync\ClockifySyncJobRepository;
use App\Repositories\Sync\ClockifySyncRunRepository;
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
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        BackupRunRepositoryInterface::class => BackupRunRepository::class,
        ClockifyApiUsageRepositoryInterface::class => ClockifyApiUsageRepository::class,
        ClockifyConnectionRepositoryInterface::class => ClockifyConnectionRepository::class,
        ClockifyDeletedEntityRepositoryInterface::class => ClockifyDeletedEntityRepository::class,
        ClockifyEntityChangeRepositoryInterface::class => ClockifyEntityChangeRepository::class,
        ClockifyMembershipRepositoryInterface::class => ClockifyMembershipRepository::class,
        ClockifyProjectMemberRepositoryInterface::class => ClockifyProjectMemberRepository::class,
        ClockifyProjectRepositoryInterface::class => ClockifyProjectRepository::class,
        ClockifyRawRecordRepositoryInterface::class => ClockifyRawRecordRepository::class,
        ClockifySyncJobRepositoryInterface::class => ClockifySyncJobRepository::class,
        ClockifySyncRunRepositoryInterface::class => ClockifySyncRunRepository::class,
        ClockifyWorkspaceRepositoryInterface::class => ClockifyWorkspaceRepository::class,
        CommandRunRepositoryInterface::class => CommandRunRepository::class,
        DataProcessingJobRepositoryInterface::class => DataProcessingJobRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        PipelineEventRepositoryInterface::class => PipelineEventRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        TimeEntryTagRepositoryInterface::class => TimeEntryTagRepository::class,
        UploadRepositoryInterface::class => UploadRepository::class,
        UserCfValueRepositoryInterface::class => UserCfValueRepository::class,
        UserGroupMemberRepositoryInterface::class => UserGroupMemberRepository::class,
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
