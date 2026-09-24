<?php

namespace App\Jobs;

use App\Enums\DataProcessingJobType;
use App\Models\DataProcessingJob;

/**
 * Maps a job's operation type to the queued job class that runs it.
 */
final class DataProcessingJobDispatcher
{
    public static function dispatch(DataProcessingJob $job): void
    {
        match ($job->type) {
            DataProcessingJobType::IMPORT => ProcessImport::dispatch($job),
            DataProcessingJobType::EXPORT,
            DataProcessingJobType::REPORT => ProcessExport::dispatch($job),
        };
    }
}
