<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Pipeline\PipelineMetricsService;
use App\Services\Pipeline\QueueDepthReader;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Telescope\Telescope;
use Spatie\Health\Checks\Check;
use Spatie\Health\Facades\Health;
use Throwable;

/**
 * The pipeline health & observability screen (PIPE-10): KPIs, queue depth,
 * recent runs/failures, API budget and the health checks — one place to answer
 * "is the pipeline healthy?".
 */
class PipelineController extends Controller
{
    public function __construct(
        protected PipelineMetricsService $metrics,
        protected QueueDepthReader $queue,
        protected DataProcessingJobRepositoryInterface $jobs,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewDeveloperTools');

        $health = $this->health();

        return Inertia::render('developer/pipeline', [
            'metrics' => $this->metrics->snapshot(),
            'queue' => $this->queue->read(),
            'health' => $health,
            'status' => $this->overallStatus($health),
            'recentRuns' => $this->recentRuns(),
            'recentFailures' => $this->recentFailures(),
            // Populated once SYNC-17 surfaces the per-connection budget.
            'apiBudget' => null,
            'tools' => $this->tools(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentRuns(): array
    {
        return $this->jobs->recentRuns(10)->map(fn (DataProcessingJob $job): array => [
            'id' => $job->id,
            'job_id' => $job->job_id,
            'name' => $job->name,
            'type' => $job->type->value,
            'type_label' => $job->type->label(),
            'entity' => $job->entity_type?->value,
            'entity_label' => $job->entity_type?->label(),
            'status' => $job->status->value,
            'status_label' => $job->status->label(),
            'duration_ms' => $this->durationMs($job),
            'records' => (int) ($job->success_count ?? 0),
            'started_at' => $job->started_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
            'action_url' => route('activity.show', $job),
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentFailures(): array
    {
        return $this->jobs->recentFailures(5)->map(function (DataProcessingJob $job): array {
            $reason = $job->failure_reason;

            return [
                'id' => $job->id,
                'job_id' => $job->job_id,
                'type_label' => $job->type->label(),
                'entity_label' => $job->entity_type?->label(),
                'reason' => $reason?->value,
                'reason_label' => $reason?->label(),
                'hint' => $reason?->hint(),
                'action' => $reason?->action(),
                'error_message' => $job->error_message,
                'failed_at' => $job->completed_at?->toIso8601String(),
                'action_url' => route('activity.show', $job),
                'retry_url' => route('activity.retry', $job),
            ];
        })->all();
    }

    private function durationMs(DataProcessingJob $job): ?int
    {
        if ($job->started_at === null || $job->completed_at === null) {
            return null;
        }

        return (int) round($job->started_at->diffInSeconds($job->completed_at, true) * 1000);
    }

    /**
     * @param  array<int, array<string, mixed>>  $health
     */
    private function overallStatus(array $health): string
    {
        $statuses = array_column($health, 'status');

        if (array_intersect($statuses, ['failed', 'crashed']) !== []) {
            return 'unhealthy';
        }

        if (in_array('warning', $statuses, true)) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * @return array<int, array{name: string, status: string, summary: string}>
     */
    private function health(): array
    {
        return Health::registeredChecks()
            ->map(function (Check $check): array {
                try {
                    $result = $check->run();

                    return [
                        'name' => $check->getName(),
                        'status' => (string) $result->status->value,
                        'summary' => $result->getShortSummary(),
                    ];
                } catch (Throwable $e) {
                    return [
                        'name' => $check->getName(),
                        'status' => 'crashed',
                        'summary' => $e->getMessage(),
                    ];
                }
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, title: string, description: string, href: string, available: bool}>
     */
    private function tools(): array
    {
        return [
            [
                'key' => 'telescope',
                'title' => 'Telescope',
                'description' => 'Inspect requests, exceptions, queries and jobs.',
                'href' => '/telescope',
                'available' => class_exists(Telescope::class) && (bool) config('telescope.enabled'),
            ],
            [
                'key' => 'pulse',
                'title' => 'Pulse',
                'description' => 'Application performance and usage metrics.',
                'href' => '/pulse',
                'available' => (bool) config('pulse.enabled'),
            ],
            [
                'key' => 'horizon',
                'title' => 'Horizon',
                'description' => 'Queue supervision and monitoring (Linux only).',
                'href' => '/horizon',
                'available' => extension_loaded('pcntl'),
            ],
        ];
    }
}
