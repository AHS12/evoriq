<?php

namespace App\Checks;

use App\Services\Pipeline\PipelineMetricsService;
use App\Services\Pipeline\QueueDepthReader;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Degrades application health when the pipeline is unhealthy (PIPE-10): too many
 * stale runs, a high 7-day failure rate, or a deep queue. Thresholds live in
 * `config/pipeline.php` under `health`.
 */
class PipelineHealthCheck extends Check
{
    public ?string $name = 'Pipeline';

    public function run(): Result
    {
        $metrics = app(PipelineMetricsService::class)->snapshot();
        $queue = app(QueueDepthReader::class)->read();

        $stale = (int) $metrics['stale'];
        $successRate = $metrics['success_rate_7d'];
        $failureRate = $successRate === null ? 0.0 : round(100 - (float) $successRate, 1);
        $queueDepth = array_sum(array_map(static fn (array $channel): int => $channel['depth'], $queue['channels']));

        $staleThresholds = (array) config('pipeline.health.stale_runs', []);
        $rateThresholds = (array) config('pipeline.health.failure_rate', []);
        $queueThresholds = (array) config('pipeline.health.queue_depth', []);

        $summary = __('Stale runs: :stale · Failure rate (7d): :rate% · Queue: :queue', [
            'stale' => $stale,
            'rate' => $failureRate,
            'queue' => $queueDepth,
        ]);

        $failed = $stale >= (int) ($staleThresholds['failed'] ?? 3)
            || $failureRate >= (float) ($rateThresholds['failed'] ?? 25.0)
            || $queueDepth >= (int) ($queueThresholds['failed'] ?? 500);

        if ($failed) {
            return Result::make()->failed($summary);
        }

        $warning = $stale >= (int) ($staleThresholds['warning'] ?? 1)
            || $failureRate >= (float) ($rateThresholds['warning'] ?? 10.0)
            || $queueDepth >= (int) ($queueThresholds['warning'] ?? 100);

        if ($warning) {
            return Result::make()->warning($summary);
        }

        return Result::make()->ok($summary);
    }
}
