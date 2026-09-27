<?php

namespace App\Jobs\Backup;

use App\Enums\QueueName;
use App\Models\BackupRun;
use App\Registry\QueueRegistry;
use App\Services\Backup\BackupRunContext;
use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\Support\BackupLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class RunBackup implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(public BackupRun $backupRun)
    {
        $config = QueueRegistry::get(QueueName::HEAVY);

        $this->tries = $config->tries;
        $this->timeout = $config->timeout;

        $this->onQueue($config->name);
    }

    /**
     * Execute the job.
     */
    public function handle(BackupService $service, BackupScheduleService $schedule): void
    {
        $run = $this->backupRun;

        $service->markRunning($run);

        BackupRunContext::set($run);

        $buffer = new BufferedOutput;

        try {
            // The logger is a scoped singleton: in a long-lived worker its
            // echo listeners would accumulate across runs and duplicate every
            // output line, so clear them before each invocation.
            app(BackupLogger::class)->clearListeners();

            config(['backup.runtime' => $schedule->runtimeConfig()]);

            $exitCode = Artisan::call('backup:run', [
                '--config' => 'backup.runtime',
                '--disable-notifications' => true,
            ], $buffer);
            $output = trim($buffer->fetch());

            // Make a silently skipped remote mirror visible in the run output.
            if ($schedule->sendToRemote() && ! $schedule->remoteConfigured()) {
                $output .= PHP_EOL.'The remote copy was skipped: remote storage is not configured (Settings -> Remote storage).';
            }

            $run->refresh();

            // A package event listener may already have reached a terminal
            // state (e.g. the backup failed event fired mid-run).
            if ($run->isFinished()) {
                return;
            }

            if ($exitCode !== 0) {
                $service->markFailed(
                    $run,
                    $output !== '' ? $output : 'The backup process failed.',
                    $output,
                    $exitCode,
                );

                return;
            }

            $service->markCompleted($run, $output, $exitCode);
        } catch (Throwable $e) {
            $run->refresh();

            if (! $run->isFinished()) {
                $service->markFailed($run, $e->getMessage(), trim($buffer->fetch()), null);
            }

            throw $e;
        } finally {
            BackupRunContext::forget();
        }
    }

    /**
     * Guarantee the run reaches a terminal state even if the worker dies
     * mid-execution.
     */
    public function failed(?Throwable $e): void
    {
        BackupRunContext::forget();

        $run = $this->backupRun->fresh();

        if ($run !== null && ! $run->isFinished()) {
            app(BackupService::class)->markFailed(
                $run,
                $e?->getMessage() ?? 'The backup job failed.',
            );
        }
    }
}
