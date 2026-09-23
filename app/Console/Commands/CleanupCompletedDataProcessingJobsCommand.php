<?php

namespace App\Console\Commands;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupCompletedDataProcessingJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-processing:cleanup-completed
                            {--days= : Number of days to keep completed jobs (defaults to the configured retention)}
                            {--dry-run : Show what would be deleted without deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete completed data processing jobs (and their files) older than the retention window';

    /**
     * Execute the console command.
     */
    public function handle(DataProcessingJobService $service): int
    {
        $days = $this->option('days');

        $days = $days !== null
            ? (int) $days
            : (int) Settings::get(
                SettingKey::EXPORT_CLEANUP_DAYS->value,
                SettingKey::EXPORT_CLEANUP_DAYS->defaultValue(),
            );

        if ($days < 1) {
            $this->error('Days must be a positive number.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $count = $service->countCompletedBefore($cutoff);

        if ($count === 0) {
            $this->info('No completed jobs found to cleanup.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} completed job(s) older than {$days} day(s).");

            $this->table(
                ['Job ID', 'Type', 'Completed At'],
                $service->completedBefore($cutoff, 5)
                    ->map(fn ($job): array => [
                        $job->job_id,
                        $job->type->value,
                        $job->completed_at?->format('Y-m-d H:i:s') ?? '-',
                    ])
                    ->all(),
            );

            return self::SUCCESS;
        }

        $deleted = 0;

        $service->completedBefore($cutoff)->each(function ($job) use ($service, &$deleted): void {
            $service->delete($job);
            $deleted++;
        });

        $this->info("Deleted {$deleted} completed job(s).");

        return self::SUCCESS;
    }
}
