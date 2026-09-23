<?php

namespace App\Jobs;

use App\Enums\ExportFormat;
use App\Enums\QueueName;
use App\Models\DataProcessingJob;
use App\Registry\QueueRegistry;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class ProcessExport implements ShouldQueue
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
    public function __construct(public DataProcessingJob $dataProcessingJob)
    {
        $config = QueueRegistry::get(QueueName::HEAVY);

        $this->tries = $config->tries;
        $this->timeout = $config->timeout;

        $this->onQueue($config->name);
    }

    /**
     * Execute the job.
     */
    public function handle(DataProcessingJobService $service): void
    {
        $job = $this->dataProcessingJob;

        $service->markProcessing($job);

        try {
            $entity = $job->entity_type;

            if ($entity === null) {
                throw new RuntimeException('The export job is missing an entity type.');
            }

            $format = $job->format ?? ExportFormat::XLSX;
            $fileName = sprintf('%s_export_%s.%s', $entity->value, $job->job_id, $format->extension());
            $basePath = trim((string) config('exports.base_path', 'exports'), '/');
            $filePath = "{$basePath}/{$fileName}";
            $fileDisk = (string) config('exports.disk', 'local');

            Excel::store($entity->makeExporter($job->filters ?? []), $filePath, $fileDisk);

            $service->attachFile($job, $fileName, $filePath, $fileDisk);
            $service->markCompleted($job);
        } catch (Throwable $e) {
            $service->markFailed($job, $e->getMessage());

            throw $e;
        }
    }
}
