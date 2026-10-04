<?php

namespace App\Services\Pipeline;

use App\Contracts\PipelineRunnable;
use App\DTOs\Pipeline\PipelineEventDTO;
use App\Enums\PipelineEventLevel;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use App\Support\OrganizationContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes events into the append-only pipeline stream.
 *
 * Responsibilities:
 * - allocate the per-run `sequence` (race-safe via the unique index + retry);
 * - redact sensitive context keys before persisting;
 * - coalesce noisy PROGRESS events by stage, percentage bucket and time.
 *
 * The recorder writes events as its own small transaction. Callers must not
 * invoke it from inside a business transaction that can roll back (the same
 * rule as mail/notifications in AGENTS.md §7.9) — record after commit so the
 * log stays truthful.
 */
class PipelineEventRecorder
{
    /**
     * How many times a sequence collision is retried before giving up.
     */
    private const MAX_SEQUENCE_ATTEMPTS = 3;

    public function __construct(
        protected PipelineEventRepositoryInterface $repository,
    ) {}

    /**
     * Record a single event for a run.
     *
     * Supported options: `message`, `level`, `stage`, `context`, `progress`,
     * `attempt`, `duration_ms`, `occurred_at`, `organization_id`.
     *
     * @param  array<string, mixed>  $options
     */
    public function record(PipelineRunType $type, string|int $runId, PipelineEventType $event, array $options = []): void
    {
        $context = $options['context'] ?? null;
        $progress = $options['progress'] ?? null;
        $stage = $options['stage'] ?? null;
        $message = $options['message'] ?? null;

        $dto = new PipelineEventDTO(
            runType: $type,
            runId: (string) $runId,
            type: $event,
            level: $this->normalizeLevel($options['level'] ?? null, $event),
            stage: is_string($stage) ? $stage : null,
            message: is_string($message) ? $message : null,
            context: is_array($context) ? $this->redact($context) : [],
            progress: is_array($progress) ? $progress : [],
            attempt: isset($options['attempt']) ? (int) $options['attempt'] : 1,
            durationMs: isset($options['duration_ms']) ? (int) $options['duration_ms'] : null,
            occurredAt: $this->normalizeOccurredAt($options['occurred_at'] ?? null),
            organizationId: $this->normalizeOrganizationId($options['organization_id'] ?? null),
        );

        if ($this->shouldDropForCap($dto)) {
            $this->recordCapWarning($dto);

            return;
        }

        $this->store($dto);
    }

    /**
     * The run was queued.
     *
     * @param  array<string, mixed>  $context
     */
    public function dispatched(PipelineRunnable $run, array $context = [], ?int $attempt = null): void
    {
        $this->forRun($run, PipelineEventType::DISPATCHED, [
            'context' => $context,
            'attempt' => $attempt,
        ]);
    }

    /**
     * The run started processing.
     */
    public function started(PipelineRunnable $run, ?string $stage = null, ?int $attempt = null): void
    {
        $this->forRun($run, PipelineEventType::STARTED, [
            'stage' => $stage,
            'attempt' => $attempt,
        ]);
    }

    /**
     * A stage began.
     *
     * @param  array<string, mixed>  $context
     */
    public function stageStarted(PipelineRunnable $run, string $stage, array $context = [], ?int $attempt = null): void
    {
        $this->forRun($run, PipelineEventType::STAGE_STARTED, [
            'stage' => $stage,
            'context' => $context,
            'attempt' => $attempt,
        ]);
    }

    /**
     * A stage finished.
     *
     * @param  array<string, mixed>  $context
     */
    public function stageCompleted(PipelineRunnable $run, string $stage, ?int $durationMs = null, array $context = []): void
    {
        $this->forRun($run, PipelineEventType::STAGE_COMPLETED, [
            'stage' => $stage,
            'duration_ms' => $durationMs,
            'context' => $context,
        ]);
    }

    /**
     * Incremental progress. Coalesced per stage/bucket/time.
     */
    public function progress(
        PipelineRunnable $run,
        int|float $processed,
        int|float|null $total = null,
        ?string $stage = null,
        ?int $attempt = null,
    ): void {
        $percentage = ($total !== null && $total > 0)
            ? round((float) $processed / (float) $total * 100, 2)
            : null;

        if ($this->shouldCoalesceProgress($run, $stage, $percentage)) {
            return;
        }

        $this->forRun($run, PipelineEventType::PROGRESS, [
            'stage' => $stage,
            'progress' => array_filter([
                'total' => $total,
                'processed' => $processed,
                'percentage' => $percentage,
            ], static fn (mixed $value): bool => $value !== null),
            'attempt' => $attempt,
        ]);
    }

    /**
     * A recoverable problem worth surfacing.
     *
     * @param  array<string, mixed>  $context
     */
    public function warning(PipelineRunnable $run, string $message, array $context = [], ?string $stage = null): void
    {
        $this->forRun($run, PipelineEventType::WARNING, [
            'message' => $message,
            'context' => $context,
            'stage' => $stage,
        ]);
    }

    /**
     * A non-terminal error.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(PipelineRunnable $run, string $message, array $context = [], ?string $stage = null, ?int $attempt = null): void
    {
        $this->forRun($run, PipelineEventType::ERROR, [
            'message' => $message,
            'context' => $context,
            'stage' => $stage,
            'attempt' => $attempt,
        ]);
    }

    /**
     * A retry was scheduled.
     *
     * @param  array<string, mixed>  $context
     */
    public function retryScheduled(PipelineRunnable $run, ?string $message = null, ?int $attempt = null, array $context = []): void
    {
        $this->forRun($run, PipelineEventType::RETRY_SCHEDULED, [
            'message' => $message,
            'context' => $context,
            'attempt' => $attempt,
        ]);
    }

    /**
     * A retry attempt began.
     */
    public function retryStarted(PipelineRunnable $run, ?int $attempt = null): void
    {
        $this->forRun($run, PipelineEventType::RETRY_STARTED, [
            'attempt' => $attempt,
        ]);
    }

    /**
     * A downloadable artifact is available.
     *
     * @param  array<string, mixed>  $context
     */
    public function artifactReady(PipelineRunnable $run, string $message, array $context = []): void
    {
        $this->forRun($run, PipelineEventType::ARTIFACT_READY, [
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * The run finished successfully.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $progress
     */
    public function completed(PipelineRunnable $run, ?string $message = null, array $context = [], array $progress = []): void
    {
        $this->forRun($run, PipelineEventType::COMPLETED, [
            'message' => $message,
            'context' => $context,
            'progress' => $progress,
        ]);
    }

    /**
     * The run failed terminally.
     *
     * @param  array<string, mixed>  $context
     */
    public function failed(PipelineRunnable $run, ?string $message = null, array $context = []): void
    {
        $this->forRun($run, PipelineEventType::FAILED, [
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * The run was cancelled.
     */
    public function cancelled(PipelineRunnable $run, ?string $message = null): void
    {
        $this->forRun($run, PipelineEventType::CANCELLED, [
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function forRun(PipelineRunnable $run, PipelineEventType $event, array $options = []): void
    {
        $options['organization_id'] ??= $this->organizationIdOf($run);

        $this->record($run->pipelineRunType(), $run->pipelineRunId(), $event, $options);
    }

    /**
     * Whether an event should be dropped because the run has hit its per-run
     * cap. Only noise (progress/debug/info) is dropped; stage, warning, error
     * and terminal events always persist.
     */
    private function shouldDropForCap(PipelineEventDTO $dto): bool
    {
        $cap = (int) config('pipeline.max_events_per_run', 0);

        if ($cap <= 0 || $this->isCapWarning($dto)) {
            return false;
        }

        if ($this->repository->countForRun($dto->runType, $dto->runId) < $cap) {
            return false;
        }

        return $dto->type === PipelineEventType::PROGRESS
            || $dto->level === PipelineEventLevel::DEBUG
            || $dto->level === PipelineEventLevel::INFO;
    }

    private function isCapWarning(PipelineEventDTO $dto): bool
    {
        return $dto->type === PipelineEventType::WARNING
            && ($dto->context['event_cap'] ?? false) === true;
    }

    /**
     * Record the single "cap reached" warning, unless the latest event is
     * already that warning.
     */
    private function recordCapWarning(PipelineEventDTO $dto): void
    {
        $last = $this->repository->latestForRun($dto->runType, $dto->runId);

        if ($last !== null
            && $last->type === PipelineEventType::WARNING
            && ($last->context['event_cap'] ?? false) === true) {
            return;
        }

        $this->store(new PipelineEventDTO(
            runType: $dto->runType,
            runId: $dto->runId,
            type: PipelineEventType::WARNING,
            level: PipelineEventLevel::WARNING,
            message: __('Event cap reached — further progress events for this run are not stored.'),
            context: ['event_cap' => true, 'max_events' => (int) config('pipeline.max_events_per_run', 0)],
            attempt: $dto->attempt,
            organizationId: $dto->organizationId,
        ));
    }

    /**
     * Persist the DTO, allocating a per-run sequence. On a concurrent write
     * the unique index rejects the duplicate and the write is retried with a
     * fresh sequence.
     */
    private function store(PipelineEventDTO $dto): void
    {
        for ($attempt = 1; $attempt <= self::MAX_SEQUENCE_ATTEMPTS; $attempt++) {
            try {
                DB::transaction(function () use ($dto): void {
                    $sequence = $this->repository->nextSequence($dto->runType, $dto->runId);

                    $this->repository->append($dto->withSequence($sequence)->toArray());
                });

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_SEQUENCE_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Whether a PROGRESS event should be skipped because a recent one already
     * covers the same stage/bucket within the minimum interval.
     */
    private function shouldCoalesceProgress(PipelineRunnable $run, ?string $stage, ?float $percentage): bool
    {
        $last = $this->repository->latestProgressForRun($run->pipelineRunType(), $run->pipelineRunId());

        if ($last === null) {
            return false;
        }

        if (($last->stage ?? null) !== $stage) {
            return false;
        }

        $bucket = max(1, (int) config('pipeline.progress_event_bucket', 5));
        $lastProgress = $last->progress;
        $lastPercentage = is_array($lastProgress) ? (float) ($lastProgress['percentage'] ?? 0) : 0.0;

        if ((int) floor(($percentage ?? 0.0) / $bucket) !== (int) floor($lastPercentage / $bucket)) {
            return false;
        }

        $minSeconds = max(0, (int) config('pipeline.progress_event_min_seconds', 5));

        if ($minSeconds === 0 || $last->occurred_at->diffInSeconds(now()) >= $minSeconds) {
            return false;
        }

        return true;
    }

    /**
     * Recursively drop the configured sensitive keys (any nesting depth).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $keys = array_map(
            static fn (mixed $key): string => strtolower((string) $key),
            (array) config('pipeline.redacted_attributes', []),
        );

        if ($keys === []) {
            return $context;
        }

        return $this->redactValues($context, $keys);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function redactValues(array $context, array $keys): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $keys, true)) {
                unset($context[$key]);

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->redactValues($value, $keys);
            }
        }

        return $context;
    }

    private function normalizeLevel(mixed $level, PipelineEventType $event): PipelineEventLevel
    {
        if ($level instanceof PipelineEventLevel) {
            return $level;
        }

        if (is_string($level) && $level !== '') {
            return PipelineEventLevel::tryFrom($level) ?? $event->defaultLevel();
        }

        return $event->defaultLevel();
    }

    private function normalizeOccurredAt(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function normalizeOrganizationId(mixed $value): int|string|null
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Prefer the run's own organization (so queued jobs stamp the org the run
     * was created in); fall back to the ambient context.
     */
    private function organizationIdOf(PipelineRunnable $run): int|string|null
    {
        if ($run instanceof Model) {
            $id = $run->getAttribute('organization_id');

            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        return app(OrganizationContext::class)->id();
    }
}
