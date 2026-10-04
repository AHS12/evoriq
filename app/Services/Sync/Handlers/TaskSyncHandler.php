<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\ClockifyProjectRepositoryInterface;
use App\Repositories\Entity\TaskSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use App\Support\Clockify\Iso8601Duration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Ingests the task dimension (ENT-05). Tasks are nested under projects, so the
 * handler iterates the workspace's (already-synced) projects and paginates
 * `GET /workspaces/{ws}/projects/{projectId}/tasks` for each, aggregating the
 * result into a single reference snapshot. The project/assignee Clockify ids are
 * resolved to internal ids by {@see TaskSyncRepository}.
 */
class TaskSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
        protected ClockifyProjectRepositoryInterface $projects,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::TASKS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return TaskSyncRepository::class;
    }

    /**
     * The whole task set is gathered on the first call; a nested resource has no
     * single flat page counter, so subsequent calls complete the job.
     */
    public function fetchPage(SyncContext $context, int $page): iterable
    {
        if ($page > 1) {
            return [];
        }

        $tasks = [];

        foreach ($this->projects->listClockifyIds($context->workspace) as $projectClockifyId) {
            foreach ($this->tasksFor($context, $projectClockifyId) as $task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        $currency = $context->workspace->currency;
        $hourly = $this->rate($raw['hourlyRate'] ?? null, $currency);
        $cost = $this->rate($raw['costRate'] ?? null, $currency);

        return [
            'clockify_id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'status' => $this->string($raw['status'] ?? null),
            'billable' => (bool) ($raw['billable'] ?? false),
            'estimated_hours' => $this->estimatedHours($raw),
            'billable_rate_amount' => $hourly['amount'],
            'billable_rate_currency' => $hourly['currency'],
            'cost_rate_amount' => $cost['amount'],
            'cost_rate_currency' => $cost['currency'],
            'completed_at' => $this->date($raw['completedAt'] ?? null),
            'project_clockify_id' => $this->string($raw['projectId'] ?? null),
            'assignee_clockify_id' => $this->assignee($raw),
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::SOFT;
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function tasksFor(SyncContext $context, string $projectClockifyId): iterable
    {
        $endpoint = "/workspaces/{$context->workspace->clockify_id}/projects/{$projectClockifyId}/tasks";

        $pages = $this->client
            ->forConnection($context->connection, $context->workspace)
            ->paginate($endpoint, ['page-size' => $context->pageSize]);

        foreach ($pages as $pageItems) {
            foreach ($pageItems as $task) {
                if (is_array($task)) {
                    yield $task;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function estimatedHours(array $raw): ?float
    {
        $direct = $raw['estimatedHours'] ?? null;

        if (is_numeric($direct)) {
            return round((float) $direct, 2);
        }

        foreach (['estimate', 'duration'] as $key) {
            $value = $raw[$key] ?? null;
            $iso = is_array($value) ? ($value['estimate'] ?? null) : $value;
            $seconds = Iso8601Duration::toSeconds($iso);

            if ($seconds !== null) {
                return round($seconds / 3600, 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function assignee(array $raw): ?string
    {
        $single = $this->string($raw['assigneeId'] ?? null);

        if ($single !== null) {
            return $single;
        }

        $list = $raw['assigneeIds'] ?? $raw['assignees'] ?? null;

        if (! is_array($list)) {
            return null;
        }

        foreach ($list as $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? $entry['userId'] ?? null) : $entry;

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, currency, since}` shape.
     *
     * @return array{amount: float|null, currency: string|null}
     */
    private function rate(mixed $value, ?string $fallbackCurrency): array
    {
        $amount = is_array($value) ? ($value['amount'] ?? null) : $value;
        $amount = is_numeric($amount) ? (float) $amount : null;

        $currency = is_array($value)
            ? $this->string($value['currency'] ?? $value['currencyCode'] ?? null)
            : null;

        return [
            'amount' => $amount,
            'currency' => $currency ?? ($amount !== null ? $fallbackCurrency : null),
        ];
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
