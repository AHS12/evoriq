<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyProject;
use App\Models\ClockifyTask;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\TimeEntryTagRepositoryInterface;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Persists the time-entry fact (ENT-07). The handler maps each entry to
 * attributes plus reserved `user_clockify_id`/`project_clockify_id`/
 * `task_clockify_id`/`tag_clockify_ids` keys; this repository batch-resolves the
 * ids to internal ones, upserts the entries, then replaces each entry's tag
 * joins. Entries whose user cannot be resolved are skipped (the user FK is
 * required).
 */
final class TimeEntrySyncRepository extends AbstractSyncUpsertRepository
{
    public function __construct(
        protected TimeEntryTagRepositoryInterface $tags,
    ) {}

    protected function syncModel(): string
    {
        return ClockifyTimeEntry::class;
    }

    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        $this->persist($workspace, [$attributes]);

        return $this->syncQuery()
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', (string) ($attributes['clockify_id'] ?? ''))
            ->firstOrFail();
    }

    public function upsertMany(ClockifyWorkspace $workspace, iterable $rows): UpsertCounts
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = $row;
        }

        return $this->persist($workspace, $normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persist(ClockifyWorkspace $workspace, array $rows): UpsertCounts
    {
        $userIds = [];
        $projectIds = [];
        $taskIds = [];

        foreach ($rows as $row) {
            $this->collect($userIds, $row['user_clockify_id'] ?? null);
            $this->collect($projectIds, $row['project_clockify_id'] ?? null);
            $this->collect($taskIds, $row['task_clockify_id'] ?? null);
        }

        $users = $this->resolve(ClockifyUser::class, $workspace, array_keys($userIds));
        $projects = $this->resolve(ClockifyProject::class, $workspace, array_keys($projectIds));
        $tasks = $this->resolve(ClockifyTask::class, $workspace, array_keys($taskIds));

        $entryRows = [];
        $tagJoins = [];

        foreach ($rows as $row) {
            $userClockifyId = $row['user_clockify_id'] ?? null;
            $projectClockifyId = $row['project_clockify_id'] ?? null;
            $taskClockifyId = $row['task_clockify_id'] ?? null;
            $tagClockifyIds = array_key_exists('tag_clockify_ids', $row) ? $row['tag_clockify_ids'] : null;
            unset(
                $row['user_clockify_id'],
                $row['project_clockify_id'],
                $row['task_clockify_id'],
                $row['tag_clockify_ids'],
            );

            $internalUserId = is_string($userClockifyId) ? ($users[$userClockifyId] ?? null) : null;

            if ($internalUserId === null) {
                continue;
            }

            $row['user_id'] = $internalUserId;
            $row['project_id'] = is_string($projectClockifyId) ? ($projects[$projectClockifyId] ?? null) : null;
            $row['task_id'] = is_string($taskClockifyId) ? ($tasks[$taskClockifyId] ?? null) : null;

            $entryRows[] = $row;

            if (is_array($tagClockifyIds)) {
                $tagJoins[(string) $row['clockify_id']] = array_values(array_unique(array_filter(
                    $tagClockifyIds,
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )));
            }
        }

        $counts = parent::upsertMany($workspace, $entryRows);

        foreach ($tagJoins as $clockifyId => $tagClockifyIds) {
            $entry = $this->syncQuery()
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('clockify_id', $clockifyId)
                ->first();

            if ($entry instanceof ClockifyTimeEntry) {
                $this->tags->syncForTimeEntry($workspace, $entry, $tagClockifyIds);
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, bool>  $bucket
     */
    private function collect(array &$bucket, mixed $value): void
    {
        if (is_string($value) && $value !== '') {
            $bucket[$value] = true;
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolve(string $model, ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $records = $model::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($records as $record) {
            $map[(string) $record->getAttribute('clockify_id')] = (int) $record->getKey();
        }

        return $map;
    }
}
