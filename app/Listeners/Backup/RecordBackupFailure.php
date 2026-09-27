<?php

namespace App\Listeners\Backup;

use App\Services\Backup\BackupRunContext;
use App\Services\Backup\BackupService;
use Spatie\Backup\Events\BackupHasFailed;

class RecordBackupFailure
{
    public function __construct(
        protected BackupService $service,
    ) {}

    public function handle(BackupHasFailed $event): void
    {
        $run = BackupRunContext::current();

        if ($run === null || $run->isFinished()) {
            return;
        }

        $this->service->markFailed(
            $run,
            $event->exception->getMessage() ?: 'The backup process failed.',
        );
    }
}
