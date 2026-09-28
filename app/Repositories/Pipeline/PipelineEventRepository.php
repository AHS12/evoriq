<?php

namespace App\Repositories\Pipeline;

use App\Enums\PipelineEventLevel;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Models\PipelineEvent;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PipelineEventRepository implements PipelineEventRepositoryInterface
{
    public function append(array $data): PipelineEvent
    {
        return PipelineEvent::query()->create($data);
    }

    public function nextSequence(PipelineRunType $type, string|int $runId): int
    {
        $max = PipelineEvent::query()
            ->forRun($type, $runId)
            ->max('sequence');

        return (int) $max + 1;
    }

    public function latestForRun(PipelineRunType $type, string|int $runId): ?PipelineEvent
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->orderByDesc('sequence')
            ->first();
    }

    public function latestProgressForRun(PipelineRunType $type, string|int $runId): ?PipelineEvent
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->ofType(PipelineEventType::PROGRESS)
            ->orderByDesc('sequence')
            ->first();
    }

    public function forRun(PipelineRunType $type, string|int $runId, int $perPage = 50, array $filters = []): LengthAwarePaginator
    {
        return $this->buildRunQuery($type, $runId, $filters)->paginate($perPage);
    }

    public function allForRun(PipelineRunType $type, string|int $runId, int $limit = 0): Collection
    {
        $query = PipelineEvent::query()
            ->forRun($type, $runId)
            ->orderBy('sequence');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function latestForRuns(array $runKeys): array
    {
        $latest = [];

        foreach ($runKeys as $key) {
            $type = $key['run_type'] instanceof PipelineRunType
                ? $key['run_type']
                : PipelineRunType::from((string) $key['run_type']);

            $runId = (string) $key['run_id'];
            $event = $this->latestForRun($type, $runId);

            if ($event !== null) {
                $latest["{$type->value}:{$runId}"] = $event;
            }
        }

        return $latest;
    }

    public function tailForRun(PipelineRunType $type, string|int $runId, int $limit): Collection
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->orderByDesc('sequence')
            ->limit(max(1, $limit))
            ->get()
            ->sortBy('sequence')
            ->values();
    }

    public function afterSequence(PipelineRunType $type, string|int $runId, int $after, int $limit): Collection
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->limit(max(1, $limit))
            ->get();
    }

    public function beforeSequence(PipelineRunType $type, string|int $runId, int $before, int $limit): Collection
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->where('sequence', '<', $before)
            ->orderByDesc('sequence')
            ->limit(max(1, $limit))
            ->get()
            ->sortBy('sequence')
            ->values();
    }

    public function existsBefore(PipelineRunType $type, string|int $runId, int $sequence): bool
    {
        return PipelineEvent::query()
            ->forRun($type, $runId)
            ->where('sequence', '<', $sequence)
            ->exists();
    }

    public function pruneBefore(CarbonInterface $cutoff): int
    {
        return PipelineEvent::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PipelineEvent>
     */
    private function buildRunQuery(PipelineRunType $type, string|int $runId, array $filters): Builder
    {
        $query = PipelineEvent::query()->forRun($type, $runId);

        $level = $filters['level'] ?? null;

        if ($level instanceof PipelineEventLevel) {
            $query->level($level);
        } elseif (is_string($level) && $level !== '' && ($enum = PipelineEventLevel::tryFrom($level)) !== null) {
            $query->level($enum);
        }

        $eventType = $filters['type'] ?? null;

        if ($eventType instanceof PipelineEventType) {
            $query->ofType($eventType);
        } elseif (is_string($eventType) && $eventType !== '' && ($enum = PipelineEventType::tryFrom($eventType)) !== null) {
            $query->ofType($enum);
        }

        if (isset($filters['stage']) && is_string($filters['stage']) && $filters['stage'] !== '') {
            $query->where('stage', $filters['stage']);
        }

        if (isset($filters['after_sequence']) && is_numeric($filters['after_sequence'])) {
            $query->where('sequence', '>', (int) $filters['after_sequence']);
        }

        if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
            $query->where('message', 'like', '%'.$filters['search'].'%');
        }

        $direction = strtolower((string) ($filters['order_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('sequence', $direction);
    }
}
