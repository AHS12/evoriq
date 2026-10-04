<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyProject;
use App\Models\ClockifyTask;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Persists the task dimension (ENT-05). The handler maps each task to
 * attributes plus reserved `project_clockify_id`/`assignee_clockify_id` keys;
 * this repository batch-resolves them to internal ids and upserts. Tasks whose
 * project cannot be resolved are skipped (the project FK is required).
 */
final class TaskSyncRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ClockifyTask::class;
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
        $projectClockifyIds = [];
        $assigneeClockifyIds = [];

        foreach ($rows as $row) {
            $projectId = $row['project_clockify_id'] ?? null;
            $assigneeId = $row['assignee_clockify_id'] ?? null;

            if (is_string($projectId) && $projectId !== '') {
                $projectClockifyIds[$projectId] = true;
            }

            if (is_string($assigneeId) && $assigneeId !== '') {
                $assigneeClockifyIds[$assigneeId] = true;
            }
        }

        $projects = $this->resolveProjectIds($workspace, array_keys($projectClockifyIds));
        $users = $this->resolveUserIds($workspace, array_keys($assigneeClockifyIds));

        $taskRows = [];

        foreach ($rows as $row) {
            $projectId = $row['project_clockify_id'] ?? null;
            $assigneeId = $row['assignee_clockify_id'] ?? null;
            unset($row['project_clockify_id'], $row['assignee_clockify_id']);

            $internalProjectId = is_string($projectId) ? ($projects[$projectId] ?? null) : null;

            if ($internalProjectId === null) {
                continue;
            }

            $row['project_id'] = $internalProjectId;
            $row['assignee_user_id'] = is_string($assigneeId) ? ($users[$assigneeId] ?? null) : null;

            $taskRows[] = $row;
        }

        return parent::upsertMany($workspace, $taskRows);
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveProjectIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $projects = ClockifyProject::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($projects as $project) {
            $map[(string) $project->getAttribute('clockify_id')] = (int) $project->getKey();
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveUserIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $users = ClockifyUser::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($users as $user) {
            $map[(string) $user->getAttribute('clockify_id')] = (int) $user->getKey();
        }

        return $map;
    }
}
