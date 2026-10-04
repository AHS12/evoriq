<?php

namespace App\Console\Commands\Pipeline;

use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Prunes pipeline events beyond the retention window (PIPE-12) so the
 * append-only stream — the largest observability table — stays bounded.
 */
class PrunePipelineEventsCommand extends Command
{
    protected $signature = 'pipeline:prune-events
        {--days= : Override the configured retention window}
        {--dry-run : Report what would be pruned without deleting}';

    protected $description = 'Prune pipeline events older than the retention window';

    public function handle(PipelineEventRepositoryInterface $events): int
    {
        $days = (int) ($this->option('days') ?? config('pipeline.event_retention_days', 30));
        $days = max(1, $days);

        $cutoff = Carbon::now()->subDays($days);
        $count = $events->countBefore($cutoff);

        if ($this->option('dry-run')) {
            $this->info("Would prune {$count} pipeline event(s) older than {$days} day(s).");

            return self::SUCCESS;
        }

        $deleted = $events->pruneBefore($cutoff);

        $this->info("Pruned {$deleted} pipeline event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
