<?php

namespace App\Repositories\Entity;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyCustomField;
use App\Models\ClockifyTimeEntry;
use App\Models\ClockifyTimeEntryCustomFieldValue;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use RuntimeException;

/**
 * Persists time-entry custom-field values (ENT-10). Each entry's value set is
 * authoritative, so the repository replaces its rows (delete–insert) — removed
 * values disappear and re-runs never duplicate. The reserved
 * `time_entry_clockify_id`/`custom_field_clockify_id` keys are resolved to
 * internal ids in a batch; unknown fields are skipped. Values cascade with the
 * entry and field, so deletion is `NONE`.
 */
final class TimeEntryCfValueSyncRepository implements SyncUpsertRepositoryInterface
{
    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        $this->persist($workspace, [$attributes]);

        $entry = $this->resolveEntry($workspace, (string) ($attributes['time_entry_clockify_id'] ?? ''));

        if ($entry === null) {
            throw new RuntimeException('The time entry for the custom field value could not be resolved.');
        }

        return ClockifyTimeEntryCustomFieldValue::query()
            ->withoutOrganizationScope()
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
        $fieldClockifyIds = [];

        foreach ($rows as $row) {
            $entryId = $row['time_entry_clockify_id'] ?? null;

            if (is_string($entryId) && $entryId !== '') {
                $entryClockifyIds[$entryId] = true;
            }

            foreach ($this->values($row) ?? [] as $value) {
                $fieldId = $value['custom_field_clockify_id'] ?? null;

                if (is_string($fieldId) && $fieldId !== '') {
                    $fieldClockifyIds[$fieldId] = true;
                }
            }
        }

        $entries = $this->resolveEntries($workspace, array_keys($entryClockifyIds));
        $fields = $this->resolveFieldIds($workspace, array_keys($fieldClockifyIds));

        $created = 0;

        foreach ($rows as $row) {
            $entryId = $row['time_entry_clockify_id'] ?? null;
            $entry = is_string($entryId) ? ($entries[$entryId] ?? null) : null;
            $values = $this->values($row);

            // `null` means the payload did not carry customFieldValues; leave the
            // entry's existing values untouched. An array (possibly empty) is
            // authoritative, so the set is replaced.
            if ($entry === null || $values === null) {
                continue;
            }

            ClockifyTimeEntryCustomFieldValue::query()
                ->withoutOrganizationScope()
                ->where('time_entry_id', $entry->id)
                ->delete();

            foreach ($values as $value) {
                $fieldId = $fields[(string) ($value['custom_field_clockify_id'] ?? '')] ?? null;

                if ($fieldId === null) {
                    continue;
                }

                ClockifyTimeEntryCustomFieldValue::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $workspace->organization_id,
                        'workspace_id' => $workspace->id,
                        'time_entry_id' => $entry->id,
                        'custom_field_id' => $fieldId,
                        'value' => $value['value'] ?? null,
                        'raw_data' => $value['raw_data'] ?? $value,
                    ]);

                $created++;
            }
        }

        return new UpsertCounts(created: $created);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>|null
     */
    private function values(array $row): ?array
    {
        $values = $row['custom_field_values'] ?? null;

        return is_array($values) ? $values : null;
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

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, int>
     */
    private function resolveFieldIds(ClockifyWorkspace $workspace, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $fields = ClockifyCustomField::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereIn('clockify_id', $clockifyIds)
            ->get(['id', 'clockify_id']);

        $map = [];

        foreach ($fields as $field) {
            $map[(string) $field->getAttribute('clockify_id')] = (int) $field->getKey();
        }

        return $map;
    }
}
