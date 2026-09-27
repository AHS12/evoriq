<?php

namespace App\Listeners\Backup;

use App\Services\Backup\BackupRunContext;
use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\BackupService;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;

class RecordBackupSuccess
{
    public function __construct(
        protected BackupService $service,
    ) {}

    public function handle(BackupWasSuccessful $event): void
    {
        $run = BackupRunContext::current();

        if ($run === null || $run->type->value !== 'backup') {
            return;
        }

        // The event fires once per destination disk; the archive details
        // recorded on the run come from the local disk, which is also what
        // the download endpoint serves.
        if ($event->diskName !== BackupScheduleService::LOCAL_DISK) {
            return;
        }

        $newest = BackupDestination::create($event->diskName, $event->backupName)->newestBackup();

        if ($newest === null || ! $newest->exists()) {
            return;
        }

        $this->service->recordArchive(
            $run,
            $event->diskName,
            $newest->path(),
            (int) round($newest->sizeInBytes()),
        );
    }
}
