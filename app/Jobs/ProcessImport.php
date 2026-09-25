<?php

namespace App\Jobs;

use App\Enums\DataProcessingJobStatus;
use App\Enums\QueueName;
use App\Exceptions\ImportCancelledException;
use App\Imports\Support\ImportCounter;
use App\Jobs\Concerns\TracksDataProcessingJob;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Registry\QueueRegistry;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\Activitylog\Support\CauserResolver;
use Throwable;

#[FailOnTimeout]
class ProcessImport implements ShouldQueue
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
     * Execute the job. All model events fired during the run (e.g. imported
     * users) are attributed to the job owner, since a queue has no
     * authenticated user.
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

        $total = ImportCounter::count($job->input_path, $job->input_disk ?? 'local');

        $service->markProcessing($job, totalItems: $total > 0 ? $total : null, stage: 'Reading file');

        try {
            $entity = $job->entity_type;

            if ($entity === null || ! $job->isImport()) {
                throw new RuntimeException('The import job is missing an entity type.');
            }

            $importer = $entity->makeImporter($job, function (int $processed, ?string $stage) use ($service, $job): void {
                $cancelled = DataProcessingJob::query()
                    ->whereKey($job->getKey())
                    ->whereNotNull('cancel_requested_at')
                    ->exists();

                if ($cancelled) {
                    throw new ImportCancelledException('Import cancelled.');
                }

                $service->markProgress($job, $processed, $stage);
            });

            Excel::import($importer, (string) $job->input_path, $job->input_disk ?? 'local');

            $result = $importer->result();

            $service->storeImportReport($job, $result);

            $service->markCompleted(
                $job,
                totalItems: $total,
                processedItems: $result->processed,
                successCount: $result->created,
                errorCount: $result->failed,
                errors: $result->errors,
            );

            $this->notifySafely($service, $job);
        } catch (ImportCancelledException) {
            $service->markCancelled($job);
            $this->notifySafely($service, $job);
        }
        // Any other throwable bubbles to the worker; failed() marks the row
        // failed (and notifies) once retries are exhausted.
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
