<?php

namespace App\Services\Backup;

use App\DTOs\Notification\NotificationDTO;
use App\DTOs\Notification\NotificationTargetDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\BackupRunStatus;
use App\Enums\BackupRunTrigger;
use App\Enums\BackupRunType;
use App\Enums\NotificationTargetType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Jobs\Backup\RunBackup;
use App\Mail\BackupCompletedMail;
use App\Models\BackupRun;
use App\Models\User;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Support\BackupLogger;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Owns the backup run lifecycle: starting scheduled/manual backup runs,
 * transitioning their state, recording audit events and sending failure
 * notifications. Cleanup and health-check runs execute in-process because they
 * are short; actual backup runs are queued on the heavy channel (RunBackup).
 */
class BackupService
{
    public function __construct(
        protected BackupRunRepositoryInterface $runs,
        protected BackupScheduleService $schedule,
        protected AuditLogService $audit,
        protected NotificationService $notifications,
    ) {}

    public function startScheduledBackup(): BackupRun
    {
        $run = $this->runs->create([
            'type' => BackupRunType::BACKUP->value,
            'trigger' => BackupRunTrigger::SCHEDULED->value,
            'disk' => BackupScheduleService::LOCAL_DISK,
        ]);

        RunBackup::dispatch($run);

        $this->audit->record(
            AuditEvent::BACKUP_TRIGGERED,
            $run,
            ['trigger' => BackupRunTrigger::SCHEDULED->value, 'disk' => $run->disk],
            channel: AuditLogName::DOMAIN,
        );

        return $run;
    }

    public function startManualBackup(User $user): BackupRun
    {
        $run = $this->runs->create([
            'type' => BackupRunType::BACKUP->value,
            'trigger' => BackupRunTrigger::MANUAL->value,
            'disk' => BackupScheduleService::LOCAL_DISK,
            'user_id' => $user->id,
            'created_by' => $user->id,
        ]);

        RunBackup::dispatch($run);

        $this->audit->record(
            AuditEvent::BACKUP_TRIGGERED,
            $run,
            ['trigger' => BackupRunTrigger::MANUAL->value, 'disk' => $run->disk],
            actor: $user,
            channel: AuditLogName::DOMAIN,
        );

        return $run;
    }

    public function markRunning(BackupRun $run): BackupRun
    {
        return $this->runs->update($run, [
            'status' => BackupRunStatus::RUNNING->value,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(BackupRun $run, ?string $output = null, ?int $exitCode = null): BackupRun
    {
        $updated = $this->runs->update($run, [
            'status' => BackupRunStatus::COMPLETED->value,
            'output' => $output,
            'exit_code' => $exitCode ?? 0,
            'error_message' => null,
            'completed_at' => now(),
        ]);

        if ($run->type === BackupRunType::BACKUP) {
            $this->audit->record(
                AuditEvent::BACKUP_COMPLETED,
                $updated,
                [
                    'trigger' => $updated->trigger->value,
                    'disk' => $updated->disk,
                    'file_name' => $updated->file_name,
                    'file_size' => $updated->file_size,
                ],
                actor: $updated->user,
                channel: AuditLogName::DOMAIN,
            );

            $this->emailArchive($updated);
        }

        return $updated;
    }

    public function markFailed(BackupRun $run, string $errorMessage, ?string $output = null, ?int $exitCode = null): BackupRun
    {
        $updated = $this->runs->update($run, [
            'status' => BackupRunStatus::FAILED->value,
            'output' => $output,
            'exit_code' => $exitCode,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        $this->audit->record(
            AuditEvent::BACKUP_FAILED,
            $updated,
            [
                'trigger' => $updated->trigger->value,
                'type' => $updated->type->value,
                'disk' => $updated->disk,
                'error' => mb_substr($errorMessage, 0, 500),
            ],
            actor: $updated->user,
            channel: AuditLogName::DOMAIN,
        );

        $this->notifyFailure($updated);

        return $updated;
    }

    /**
     * Attach the produced archive details (called from the package's events).
     */
    public function recordArchive(BackupRun $run, string $disk, string $fileName, int $fileSize): BackupRun
    {
        return $this->runs->update($run, [
            'disk' => $disk,
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ]);
    }

    /**
     * Run a backup cleanup in the current process (short-running).
     */
    public function runCleanup(): BackupRun
    {
        return $this->runMaintenance(BackupRunType::CLEANUP, 'backup:clean');
    }

    /**
     * Run a backup health check in the current process using the package's
     * monitor API directly (the monitor command cannot take a runtime config).
     */
    public function runMonitor(): BackupRun
    {
        $run = $this->runs->create([
            'type' => BackupRunType::MONITOR->value,
            'trigger' => BackupRunTrigger::SCHEDULED->value,
            'disk' => BackupScheduleService::LOCAL_DISK,
        ]);

        $this->markRunning($run);

        try {
            $config = Config::fromArray($this->schedule->runtimeConfig());

            $statuses = BackupDestinationStatusFactory::createForMonitorConfig($config->monitoredBackups);

            $failureMessages = $statuses
                ->filter(static fn (BackupDestinationStatus $status): bool => ! $status->isHealthy())
                ->flatMap(static fn (BackupDestinationStatus $status) => $status->failureMessages())
                ->values();

            if ($failureMessages->isNotEmpty()) {
                $messages = $failureMessages
                    ->map(static fn (array $failure): string => (string) $failure['message'])
                    ->filter()
                    ->implode(PHP_EOL);

                return $this->markFailed(
                    $run,
                    $messages !== '' ? $messages : 'The backup destination is unhealthy.',
                );
            }

            return $this->markCompleted($run);
        } catch (Throwable $e) {
            $run->refresh();

            if (! $run->isFinished()) {
                $this->markFailed($run, $e->getMessage());
            }

            return $run;
        }
    }

    /**
     * Run a short-lived spatie command in-process, correlating its events back
     * to a BackupRun row via BackupRunContext.
     */
    protected function runMaintenance(BackupRunType $type, string $command): BackupRun
    {
        $run = $this->runs->create([
            'type' => $type->value,
            'trigger' => BackupRunTrigger::SCHEDULED->value,
            'disk' => BackupScheduleService::LOCAL_DISK,
        ]);

        $this->markRunning($run);

        BackupRunContext::set($run);

        $buffer = new BufferedOutput;

        try {
            // The logger is a scoped singleton: in a long-lived worker its
            // echo listeners would accumulate across runs and duplicate every
            // output line, so clear them before each invocation.
            app(BackupLogger::class)->clearListeners();

            config(['backup.runtime' => $this->schedule->runtimeConfig()]);

            $exitCode = Artisan::call($command, [
                '--config' => 'backup.runtime',
                '--disable-notifications' => true,
            ], $buffer);
            $output = trim($buffer->fetch());

            $run->refresh();

            // A package event listener may already have reached a terminal
            // state (e.g. an unhealthy backup was found during monitoring).
            if ($run->isFinished()) {
                return $run;
            }

            if ($exitCode !== 0) {
                return $this->markFailed(
                    $run,
                    $output !== '' ? $output : "The [{$command}] command failed.",
                    $output,
                    $exitCode,
                );
            }

            return $this->markCompleted($run, $output, $exitCode);
        } catch (Throwable $e) {
            $run->refresh();

            if (! $run->isFinished()) {
                $this->markFailed($run, $e->getMessage(), trim($buffer->fetch()), null);
            }

            return $run;
        } finally {
            BackupRunContext::forget();
        }
    }

    /**
     * In-app notification to everyone allowed to manage backups. Never lets a
     * notification problem corrupt the run bookkeeping.
     */
    protected function notifyFailure(BackupRun $run): void
    {
        if (! $this->schedule->notifyOnFailure()) {
            return;
        }

        $type = $run->type === BackupRunType::MONITOR
            ? NotificationType::BACKUP_UNHEALTHY
            : NotificationType::BACKUP_FAILED;

        $title = $type === NotificationType::BACKUP_UNHEALTHY
            ? __('Backup health check failed')
            : __('Backup failed');

        $body = mb_substr($run->error_message ?? __('The backup process failed.'), 0, 500);

        foreach ($this->backupManagers() as $user) {
            $this->notifications->create(
                new NotificationDTO(
                    type: $type,
                    title: $title,
                    body: $body,
                    actionUrl: route('admin.settings.backup.edit'),
                    targets: [new NotificationTargetDTO(NotificationTargetType::USER, $user->id)],
                    createdBy: $run->user_id,
                ),
                $run->user_id,
            );
        }
    }

    /**
     * Email the finished archive when enabled in the settings. Recipients are
     * the static BACKUP_EMAIL_TO addresses when set, otherwise everyone
     * allowed to manage backups. Very large archives are skipped as
     * attachments (mail servers reject them) — the run history always has the
     * download. Mail failures never corrupt the run bookkeeping.
     */
    protected function emailArchive(BackupRun $run): void
    {
        if (! $this->schedule->emailEnabled() || ! $run->isDownloadable()) {
            return;
        }

        $attachmentLimit = (int) config('backup.email_max_attachment_mb', 10);

        $recipients = $this->emailRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->queue(new BackupCompletedMail($run, $attachmentLimit));
            }
        } catch (Throwable) {
            // Email delivery problems are non-fatal; the archive remains
            // downloadable from the run history.
        }
    }

    /**
     * The archive email recipients: the stored "Email recipients" setting when
     * set, then the static BACKUP_EMAIL_TO addresses, otherwise the backup
     * managers' emails.
     *
     * @return Collection<int, string>
     */
    protected function emailRecipients(): Collection
    {
        $emails = [];

        $configured = $this->schedule->emailRecipients();

        if ($configured === '') {
            $configured = trim((string) config('backup.notifications.mail_to', ''));
        }

        if ($configured !== '') {
            foreach (preg_split('/[,\s]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                $emails[] = (string) $email;
            }
        } else {
            foreach ($this->backupManagers() as $user) {
                $emails[] = (string) $user->email;
            }
        }

        return collect($emails);
    }

    /**
     * Everyone allowed to manage backups, falling back to super admins when
     * the permission has not been synced yet.
     *
     * @return Collection<int, User>
     */
    protected function backupManagers(): Collection
    {
        try {
            $recipients = User::query()->permission('backup.manage')->get();
        } catch (Throwable) {
            $recipients = User::query()->role(UserRole::SUPER_ADMIN->value)->get();
        }

        return $recipients;
    }
}
