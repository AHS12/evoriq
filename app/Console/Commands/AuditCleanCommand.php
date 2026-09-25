<?php

namespace App\Console\Commands;

use App\Enums\AuditLogName;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AuditCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:clean
                            {--channel= : Only prune this log channel (defaults to every channel)}
                            {--days= : Override the retention window in days}
                            {--dry-run : Show what would be deleted without deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete audit log entries older than each channel\'s retention window';

    /**
     * Execute the console command.
     */
    public function handle(AuditLogRepositoryInterface $repository): int
    {
        $channels = AuditLogName::cases();

        $only = $this->option('channel');

        if (is_string($only) && $only !== '') {
            $channel = AuditLogName::tryFrom($only);

            if ($channel === null) {
                $this->error("Unknown audit channel [{$only}].");

                return self::FAILURE;
            }

            $channels = [$channel];
        }

        $daysOverride = $this->option('days');
        $daysOverride = is_string($daysOverride) && $daysOverride !== ''
            ? (int) $daysOverride
            : null;

        if ($daysOverride !== null && $daysOverride < 1) {
            $this->error('Days must be a positive number.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        foreach ($channels as $channel) {
            $days = $daysOverride ?? $channel->retentionDays();
            $cutoff = Carbon::now()->subDays($days);

            if ($dryRun) {
                $this->line("[{$channel->value}] would delete entries older than {$days} day(s).");

                continue;
            }

            $count = $repository->deleteOlderThan($channel->value, $cutoff);

            $deleted += $count;

            $this->line("[{$channel->value}] deleted {$count} entr(y/ies) older than {$days} day(s).");
        }

        $this->info($dryRun
            ? 'Dry run complete. Nothing was deleted.'
            : "Pruned {$deleted} audit log entr(y/ies).");

        return self::SUCCESS;
    }
}
