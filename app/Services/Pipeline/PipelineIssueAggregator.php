<?php

namespace App\Services\Pipeline;

use App\DTOs\Pipeline\PipelineIssueGroupDTO;
use App\Enums\PipelineEventLevel;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use Illuminate\Support\Collection;

/**
 * PIPE-06 — groups a run's issues by `type + message` so the inspector can show
 * "12 rows skipped (duplicate email)" instead of a flat, repetitive table.
 *
 * Source of truth is the stored `errors` JSON for imports; when it is absent
 * (e.g. sync runs) it falls back to the run's error/warning events. Reads only
 * the already-loaded collections — no queries here.
 */
class PipelineIssueAggregator
{
    private const MAX_GROUPS = 50;

    private const MAX_SAMPLES = 10;

    /**
     * @param  Collection<int, PipelineEvent>  $events
     * @return array<int, PipelineIssueGroupDTO>
     */
    public function summarize(DataProcessingJob $job, Collection $events): array
    {
        $errors = $job->errors;

        if (is_array($errors) && $errors !== []) {
            return $this->fromErrors($errors, $events);
        }

        return $this->fromEvents($events);
    }

    /**
     * @param  array<int, mixed>  $errors
     * @param  Collection<int, PipelineEvent>  $events
     * @return array<int, PipelineIssueGroupDTO>
     */
    private function fromErrors(array $errors, Collection $events): array
    {
        /** @var array<string, array{type: string, message: string, count: int, sample_rows: array<int, array{row: int, message: string}>}> $groups */
        $groups = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $type = (string) ($error['type'] ?? 'error');
            $message = (string) ($error['message'] ?? '');
            $key = $type."\0".$message;

            $groups[$key] ??= [
                'type' => $type,
                'message' => $message,
                'count' => 0,
                'sample_rows' => [],
            ];

            $groups[$key]['count']++;

            if (count($groups[$key]['sample_rows']) < self::MAX_SAMPLES) {
                $groups[$key]['sample_rows'][] = [
                    'row' => (int) ($error['row'] ?? 0),
                    'message' => $message,
                ];
            }
        }

        return $this->build($groups, $this->eventTimestamps($events));
    }

    /**
     * @param  Collection<int, PipelineEvent>  $events
     * @return array<int, PipelineIssueGroupDTO>
     */
    private function fromEvents(Collection $events): array
    {
        /** @var array<string, array{type: string, message: string, count: int, sample_rows: array<int, array{row: int, message: string}>}> $groups */
        $groups = [];
        $timestamps = [];

        foreach ($events as $event) {
            if (! $this->isIssue($event)) {
                continue;
            }

            $type = $event->type->value;
            $message = (string) ($event->message ?? $event->type->label());
            $key = $type."\0".$message;

            $groups[$key] ??= [
                'type' => $type,
                'message' => $message,
                'count' => 0,
                'sample_rows' => [],
            ];

            $groups[$key]['count']++;

            $occurredAt = $event->occurred_at->toIso8601String();
            $timestamps[$type.'|'.$message]['first'] ??= $occurredAt;
            $timestamps[$type.'|'.$message]['last'] = $occurredAt;
        }

        return $this->build($groups, $timestamps);
    }

    /**
     * @param  array<string, array{type: string, message: string, count: int, sample_rows: array<int, array{row: int, message: string}>}>  $groups
     * @param  array<string, array{first?: string, last?: string}>  $timestamps
     * @return array<int, PipelineIssueGroupDTO>
     */
    private function build(array $groups, array $timestamps): array
    {
        $result = [];

        foreach ($groups as $group) {
            $seen = $timestamps[$group['type'].'|'.$group['message']] ?? [];

            $result[] = new PipelineIssueGroupDTO(
                type: $group['type'],
                message: $group['message'],
                count: $group['count'],
                sampleRows: $group['sample_rows'],
                firstSeen: $seen['first'] ?? null,
                lastSeen: $seen['last'] ?? null,
            );
        }

        usort($result, static fn (PipelineIssueGroupDTO $a, PipelineIssueGroupDTO $b): int => $b->count <=> $a->count);

        return array_slice($result, 0, self::MAX_GROUPS);
    }

    /**
     * First/last occurrence of each warning/error event, keyed by `type|message`.
     *
     * @param  Collection<int, PipelineEvent>  $events
     * @return array<string, array{first?: string, last?: string}>
     */
    private function eventTimestamps(Collection $events): array
    {
        $timestamps = [];

        foreach ($events as $event) {
            if (! $this->isIssue($event)) {
                continue;
            }

            $key = $event->type->value.'|'.(string) ($event->message ?? '');
            $occurredAt = $event->occurred_at->toIso8601String();
            $timestamps[$key]['first'] ??= $occurredAt;
            $timestamps[$key]['last'] = $occurredAt;
        }

        return $timestamps;
    }

    private function isIssue(PipelineEvent $event): bool
    {
        return in_array($event->level, [PipelineEventLevel::WARNING, PipelineEventLevel::ERROR], true);
    }
}
