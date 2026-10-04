<?php

namespace App\Repositories\Concerns;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

/**
 * Shared idempotent upsert behaviour (SYNC-08). Every synced entity repository
 * keys rows by `(organization_id, workspace_id, clockify_id)` and refreshes
 * `synced_at` on each write, so re-running a page never duplicates.
 *
 * The consuming repository only declares its Eloquent model via `syncModel()`.
 */
trait UpsertsByClockifyId
{
    /**
     * The Eloquent model this repository upserts.
     *
     * @return class-string<Model>
     */
    abstract protected function syncModel(): string;

    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model
    {
        return $this->syncQuery()->updateOrCreate(
            $this->syncKey($workspace, $attributes),
            $this->syncValues($workspace, $attributes),
        );
    }

    public function upsertMany(ClockifyWorkspace $workspace, iterable $rows): UpsertCounts
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($rows as $row) {
            $existing = $this->syncQuery()
                ->where($this->syncKey($workspace, $row))
                ->first();

            $upserted = $this->syncQuery()->updateOrCreate(
                $this->syncKey($workspace, $row),
                $this->syncValues($workspace, $row),
            );

            if ($upserted->wasRecentlyCreated) {
                $created++;

                continue;
            }

            if ($existing !== null && $this->syncAttributesChanged($existing, $row)) {
                $updated++;

                continue;
            }

            $unchanged++;
        }

        return new UpsertCounts(created: $created, updated: $updated, unchanged: $unchanged);
    }

    public function softDeleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool
    {
        if (! $this->syncRestoresDeleted()) {
            return $this->deleteByClockifyId($workspace, $clockifyId);
        }

        // Only mark rows that are still live, so re-applying the same deletion
        // is a strict no-op (ENT-13).
        return $this->syncQuery()
            ->where($this->syncKey($workspace, ['clockify_id' => $clockifyId]))
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]) > 0;
    }

    public function deleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool
    {
        return $this->syncQuery()
            ->where($this->syncKey($workspace, ['clockify_id' => $clockifyId]))
            ->delete() > 0;
    }

    /**
     * The base query for every upsert/delete, scoped to the target model and
     * free of the organization and soft-delete global scopes so a previously
     * deleted row can be found and restored rather than duplicated.
     *
     * @return Builder<Model>
     */
    protected function syncQuery(): Builder
    {
        $model = $this->syncModel();

        return $model::query()
            ->withoutGlobalScope('organization')
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * The natural key a row is upserted by.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function syncKey(ClockifyWorkspace $workspace, array $attributes): array
    {
        $key = ['organization_id' => $workspace->organization_id];

        if ($this->syncUsesWorkspace()) {
            $key['workspace_id'] = $workspace->id;
        }

        $key['clockify_id'] = (string) ($attributes['clockify_id'] ?? '');

        return $key;
    }

    /**
     * Whether rows carry a parent `workspace_id`. The workspace dimension itself
     * is the parent, so it keys by `(organization_id, clockify_id)` and omits
     * `workspace_id` (ENT-01).
     */
    protected function syncUsesWorkspace(): bool
    {
        return true;
    }

    /**
     * The attributes to persist (org/workspace are authoritative, never client).
     * Re-upserting a soft-deleted entity clears its `deleted_at`, so a
     * delete→recreate cycle converges to the live state (SYNC-07).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function syncValues(ClockifyWorkspace $workspace, array $attributes): array
    {
        $values = [
            ...$attributes,
            'organization_id' => $workspace->organization_id,
            'synced_at' => now(),
        ];

        if ($this->syncUsesWorkspace()) {
            $values['workspace_id'] = $workspace->id;
        }

        if ($this->syncRestoresDeleted()) {
            $values['deleted_at'] = null;
        }

        return $values;
    }

    /**
     * Whether the target table soft-deletes via a `deleted_at` column.
     */
    protected function syncRestoresDeleted(): bool
    {
        /** @var array<class-string<Model>, bool> $cache */
        static $cache = [];

        $model = $this->syncModel();

        return $cache[$model] ??= Schema::hasColumn((new $model)->getTable(), 'deleted_at');
    }

    /**
     * Whether the incoming row carries any meaningful change over the stored one
     * (ignoring the key columns and the always-refreshed `synced_at`).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function syncAttributesChanged(Model $existing, array $attributes): bool
    {
        foreach ($attributes as $attribute => $value) {
            if (in_array($attribute, ['organization_id', 'workspace_id', 'clockify_id', 'synced_at'], true)) {
                continue;
            }

            if ($existing->getAttribute($attribute) != $value) {
                return true;
            }
        }

        return false;
    }
}
