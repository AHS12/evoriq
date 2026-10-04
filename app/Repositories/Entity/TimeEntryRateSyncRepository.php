<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryRate;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use RuntimeException;

/**
 * Persists the per-entry historical rate fact (ENT-08). Rows are keyed by
 * `(organization_id, workspace_id, time_entry_id)` — not a Clockify id — because
 * they are derived from hydrated entries. The entry's user/project/task are
 * snapshotted onto the rate row. Rates cascade with the entry, so there is no
 * independent deletion (`deletePolicy = NONE`).
 */
final class TimeEntryRateSyncRepository implements SyncUpsertRepositoryInterface
{
    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        $this->persist($workspace, [$attributes]);

        $entry = $this->resolveEntry($workspace, (string) ($attributes['time_entry_clockify_id'] ?? ''));

        if ($entry === null) {
            throw new RuntimeException('The time entry for the rate could not be resolved.');
        }

        return ClockifyTimeEntryRate::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('time_entry_id', $entry->id)
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

    public function softDeleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool
    {
        return false;
    }

    public function deleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool
    {
        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persist(ClockifyWorkspace $workspace, array $rows): UpsertCounts
    {
        $entryClockifyIds = [];

        foreach ($rows as $row) {
            $id = $row['time_entry_clockify_id'] ?? null;

            if (is_string($id) && $id !== '') {
                $entryClockifyIds[$id] = true;
            }
        }

        $entries = $this->resolveEntries($workspace, array_keys($entryClockifyIds));

        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $entryClockifyId = $row['time_entry_clockify_id'] ?? null;
            unset($row['time_entry_clockify_id']);

            $entry = is_string($entryClockifyId) ? ($entries[$entryClockifyId] ?? null) : null;

            if ($entry === null) {
                continue;
            }

            $key = [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'time_entry_id' => $entry->id,
            ];

            $rate = ClockifyTimeEntryRate::query()
                ->withoutOrganizationScope()
                ->updateOrCreate($key, [
                    ...$row,
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'time_entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'project_id' => $entry->project_id,
                    'task_id' => $entry->task_id,
                ]);

            if ($rate->wasRecentlyCreated) {
                $created++;
            } elseif ($rate->wasChanged()) {
                $updated++;
            } else {
                $unchanged++;
            }
        }

        return new UpsertCounts(created: $created, updated: $updated, unchanged: $unchanged);
    }

    private function resolveEntry(ClockifyWorkspace $workspace, string $clockifyId): ?ClockifyTimeEntry
    {
        if ($clockifyId === '') {
            return null;
        }

        $entry = ClockifyTimeEntry::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('clockify_id', $clockifyId)
            ->first();

        return $entry instanceof ClockifyTimeEntry ? $entry : null;
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, ClockifyTimeEntry>
     */
    private function resolveEntries(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $entries = ClockifyTimeEntry::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get();

        $map = [];

        foreach ($entries as $entry) {
            $map[(string) $entry->getAttribute('clockify_id')] = $entry;
        }

        return $map;
    }
}
