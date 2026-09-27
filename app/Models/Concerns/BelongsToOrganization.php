<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as organization-owned.
 *
 * - New rows are stamped with the current organization when none is supplied.
 * - Queries are scoped to the current organization by default.
 * - `scopeWithoutOrganizationScope()` escapes the scope for seeders, backfills
 *   and cross-organization admin tooling.
 *
 * @template TModel of Model
 */
trait BelongsToOrganization
{
    /**
     * Register the model's organization listeners and query scope.
     */
    protected static function bootBelongsToOrganization(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('organization_id'))) {
                $model->setAttribute('organization_id', app(OrganizationContext::class)->id());
            }
        });

        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = app(OrganizationContext::class)->id();

            if ($organizationId !== null) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('organization_id'),
                    $organizationId,
                );
            }
        });
    }

    /**
     * The organization that owns the model.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope the query to a specific organization, bypassing the ambient scope.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeForOrganization(Builder $query, int|string $id): Builder
    {
        return $query
            ->withoutGlobalScope('organization')
            ->where($query->getModel()->qualifyColumn('organization_id'), $id);
    }

    /**
     * Run the query without the ambient organization scope.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeWithoutOrganizationScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('organization');
    }
}
