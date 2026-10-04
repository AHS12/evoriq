<?php

namespace App\Services\Sync;

use App\Enums\SyncJobStatus;
use App\Models\ClockifySyncJob;
use Throwable;

/**
 * The retry/backoff/terminal policy for a sync job (SYNC-13). A transient
 * failure schedules an exponential-backoff retry while attempts remain;
 * otherwise the job reaches its single terminal failed state.
 *
 * Budget exhaustion is handled by the runner (park, no attempt burned) and
 * never reaches this policy.
 */
class SyncRetryPolicy
{
    /**
     * Apply the failure to a job.
     *
     * @return bool Whether the job was scheduled for another attempt.
     */
    public function retry(ClockifySyncJob $job, ?Throwable $exception): bool
    {
        $message = $exception?->getMessage() ?? __('The sync job failed.');

        if ($job->attempt >= $this->maxAttempts()) {
            $job->update([
                'status' => SyncJobStatus::FAILED->value,
                'last_error' => $message,
                'completed_at' => now(),
                'heartbeat_at' => now(),
            ]);

            return false;
        }

        $job->update([
            'status' => SyncJobStatus::RETRY_SCHEDULED->value,
            'last_error' => $message,
            'next_retry_at' => now()->addSeconds($this->backoffSeconds($job->attempt)),
            'heartbeat_at' => now(),
        ]);

        return true;
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('clockify.sync_job.max_attempts', 5));
    }

    /**
     * Exponential backoff with jitter, clamped to the configured ceiling.
     */
    public function backoffSeconds(int $attempt): int
    {
        $base = max(1, (int) config('clockify.sync_job.retry_base_seconds', 60));
        $max = max($base, (int) config('clockify.sync_job.retry_max_seconds', 3600));

        $delay = $base * (2 ** max(0, $attempt - 1));
        $delay = min($delay, $max);

        $jitter = $base > 1 ? random_int(0, intdiv($base, 2)) : 0;

        return (int) min($max, $delay + $jitter);
    }
}
