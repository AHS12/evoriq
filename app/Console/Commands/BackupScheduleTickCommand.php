<?php

namespace App\Console\Commands;

use App\Enums\BackupRunType;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Evaluates the backup settings on every scheduler tick and dispatches the
 * work that is due. The spatie backup commands themselves are never scheduled
 * directly — that keeps the schedule DB-driven (no restart needed when the
 * settings change).
 */
class BackupScheduleTickCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:schedule-tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled backup work that is due according to the backup settings';

    /**
     * Execute the console command.
     */
    public function handle(
        BackupScheduleService $schedule,
        BackupService $service,
        BackupRunRepositoryInterface $runs,
    ): int {
        if ($schedule->isBackupDue()) {
            $service->startScheduledBackup();

            $this->info('Scheduled backup dispatched.');
        }

        if (! $runs->hasCompletedSince(BackupRunType::CLEANUP, Carbon::today())) {
            $service->runCleanup();

            $this->line('Backup cleanup executed.');
        }

        // Health checks run regardless of the enabled flag so a disabled
        // schedule still surfaces stale-backup warnings.
        if (! $runs->hasCompletedSince(BackupRunType::MONITOR, Carbon::today())) {
            $service->runMonitor();

            $this->line('Backup health check executed.');
        }

        return self::SUCCESS;
    }
}
