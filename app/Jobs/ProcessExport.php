<?php

namespace App\Jobs;

use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Enums\QueueName;
use App\Jobs\Concerns\TracksDataProcessingJob;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Registry\QueueRegistry;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\Activitylog\Support\CauserResolver;
use Throwable;

#[FailOnTimeout]
class ProcessExport implements ShouldQueue
{
    use Queueable;
    use TracksDataProcessingJob;

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
     * Execute the job. Model events fired during the run are attributed to
     * the job owner, since a queue has no authenticated user.
     */
    public function handle(DataProcessingJobService $service, ?CauserResolver $causerResolver = null): void
    {
        $causerResolver ??= app(CauserResolver::class);

        $owner = $this->dataProcessingJob->user_id !== null
            ? User::find($this->dataProcessingJob->user_id)
            : null;

        $causerResolver->withCauser($owner, function () use ($service): void {
            $this->process($service);
        });
    }

    private function process(DataProcessingJobService $service): void
    {
        $job = $this->dataProcessingJob;

        if ($job->fresh()?->cancellationRequested() === true || $job->status === DataProcessingJobStatus::CANCELLED) {
            $service->markCancelled($job);
            $this->notifySafely($service, $job);

            return;
        }

        $entity = $job->entity_type;

        if ($entity === null) {
            throw new RuntimeException('The export job is missing an entity type.');
        }

        $exporter = $entity->makeExporter($job->filters ?? []);
        $total = $exporter->total();

        $service->markProcessing($job, totalItems: $total, stage: $exporter->stage());

        $format = $job->format ?? ExportFormat::XLSX;
        $fileName = sprintf('%s_%s_%s.%s', $entity->value, $job->type->value, $job->job_id, $format->extension());
        $basePath = trim((string) config('exports.base_path', 'exports'), '/');
        $filePath = "{$basePath}/{$fileName}";
        $fileDisk = (string) config('exports.disk', 'local');

        Excel::store($exporter, $filePath, $fileDisk);

        $size = Storage::disk($fileDisk)->exists($filePath)
            ? Storage::disk($fileDisk)->size($filePath)
            : null;

        $service->attachArtifact($job, $fileName, $filePath, $fileDisk, $size, $format->mimeType());

        $service->markCompleted($job, totalItems: $total, processedItems: $total);

        $this->notifySafely($service, $job);
    }

    /**
     * Notify the owner, swallowing notification errors so they never fail the job.
     */
    private function notifySafely(DataProcessingJobService $service, DataProcessingJob $job): void
    {
        try {
            $service->notifyFinished($job);
        } catch (Throwable) {
            // A notification failure must not fail the job.
        }
    }
}
