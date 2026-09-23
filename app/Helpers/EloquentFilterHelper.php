<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Helper for composing Eloquent query filters and text search.
 *
 * Provides utilities to:
 * - Apply "LIKE" searches across multiple model fields
 * - Search within relations via whereHas
 * - Combine exact-match select filters with text searches
 * - Filter by date, date ranges and overlapping ranges
 *
 * All conditions are added in nested closures so they bind as intended.
 */
class EloquentFilterHelper
{
    /**
     * Apply a "LIKE" search across the given model fields.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<int, literal-string>  $searchFields
     * @return Builder<TModel>
     */
    public static function applySearchFilters(
        ?string $searchText,
        Builder $model,
        array $searchFields,
    ): Builder {
        if (empty($searchText) || $searchFields === []) {
            return $model;
        }

        return $model->where(function ($query) use ($searchText, $searchFields): void {
            self::applySearchConditions($query, $searchText, $searchFields);
        });
    }

    /**
     * Apply a "LIKE" search across a relation's fields via whereHas.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<int, literal-string>  $searchFields
     * @return Builder<TModel>
     */
    public static function applyRelationSearchFilters(
        string $relation,
        ?string $searchText,
        array $searchFields,
        Builder $model,
    ): Builder {
        if (empty($searchText) || $searchFields === []) {
            return $model;
        }

        return $model->whereHas($relation, function ($query) use ($searchText, $searchFields): void {
            self::applySearchConditions($query, $searchText, $searchFields);
        });
    }

    /**
     * Apply exact-match "where" filters for every non-empty value.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<string, mixed>  $selectFields
     * @return Builder<TModel>
     */
    public static function applySelectFilters(Builder $model, array $selectFields): Builder
    {
        $selectFields = self::withoutEmptyValues($selectFields);

        if ($selectFields === []) {
            return $model;
        }

        return $model->where(function ($query) use ($selectFields): void {
            foreach ($selectFields as $field => $value) {
                $query->where($field, '=', $value);
            }
        });
    }

    /**
     * Apply exact-match filters inside a relation via whereHas.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<string, mixed>  $selectFields
     * @return Builder<TModel>
     */
    public static function applyRelationSelectFilters(
        string $relation,
        Builder $model,
        array $selectFields,
    ): Builder {
        $selectFields = self::withoutEmptyValues($selectFields);

        if ($selectFields === []) {
            return $model;
        }

        return $model->whereHas($relation, function ($query) use ($selectFields): void {
            $query->where(function ($query) use ($selectFields): void {
                foreach ($selectFields as $field => $value) {
                    $query->orWhere($field, '=', $value);
                }
            });
        });
    }

    /**
     * Apply text search and exact-match filters in one call.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<int, literal-string>  $searchFields
     * @param  array<string, mixed>  $selectFields
     * @return Builder<TModel>
     */
    public static function applyFilters(
        ?string $searchText,
        array $searchFields,
        array $selectFields,
        Builder $model,
    ): Builder {
        if ($searchText !== null && $searchFields !== []) {
            $model = self::applySearchFilters($searchText, $model, $searchFields);
        }

        if ($selectFields !== []) {
            $model = self::applySelectFilters($model, $selectFields);
        }

        return $model;
    }

    /**
     * Apply a unified "LIKE" search across model fields and relations.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, literal-string>  $modelSearchFields
     * @param  array<string, array<int, literal-string>|null>  $relationSearchConfig
     * @return Builder<TModel>
     */
    public static function applyUnifiedSearch(
        Builder $query,
        ?string $searchText,
        array $modelSearchFields,
        array $relationSearchConfig = [],
    ): Builder {
        if (empty($searchText) || $modelSearchFields === []) {
            return $query;
        }

        return $query->where(function ($query) use ($searchText, $modelSearchFields, $relationSearchConfig): void {
            self::applySearchConditions($query, $searchText, $modelSearchFields);

            foreach ($relationSearchConfig as $relationName => $relationFields) {
                $fieldsToSearch = $relationFields ?? $modelSearchFields;

                $query->orWhereHas($relationName, function ($relationQuery) use ($searchText, $fieldsToSearch): void {
                    self::applySearchConditions($relationQuery, $searchText, $fieldsToSearch);
                });
            }
        });
    }

    /**
     * Apply a prefix "LIKE" search across a relation's fields (name-optimized).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @param  array<int, literal-string>  $searchFields
     * @return Builder<TModel>
     */
    public static function applyAccurateRelationSearchFilters(
        string $relation,
        ?string $searchText,
        array $searchFields,
        Builder $model,
    ): Builder {
        if (empty($searchText) || $searchFields === []) {
            return $model;
        }

        $searchText = mb_strtolower($searchText);

        return $model->whereHas($relation, function ($query) use ($searchText, $searchFields): void {
            $query->where(function ($query) use ($searchText, $searchFields): void {
                foreach ($searchFields as $field) {
                    $query->orWhereRaw("LOWER({$field}) LIKE ?", ["{$searchText}%"]);
                }
            });
        });
    }

    /**
     * Filter records where a date column falls within a range (inclusive).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @return Builder<TModel>
     */
    public static function applyDateRangeFilter(
        Builder $model,
        string $field,
        ?string $fromDate,
        ?string $toDate,
    ): Builder {
        if (empty($fromDate) || empty($toDate)) {
            return $model;
        }

        return $model->whereBetween($field, [
            Carbon::parse($fromDate)->startOfDay(),
            Carbon::parse($toDate)->endOfDay(),
        ]);
    }

    /**
     * Filter records whose start..end range overlaps the given range.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @return Builder<TModel>
     */
    public static function applyOverlappingDateRangeFilter(
        Builder $model,
        string $startField,
        string $endField,
        ?string $fromDate,
        ?string $toDate,
    ): Builder {
        if (empty($fromDate) || empty($toDate)) {
            return $model;
        }

        return $model->where(function ($query) use ($startField, $endField, $fromDate, $toDate): void {
            $query->whereBetween($startField, [$fromDate, $toDate])
                ->orWhereBetween($endField, [$fromDate, $toDate])
                ->orWhere(function ($query) use ($startField, $endField, $fromDate, $toDate): void {
                    $query->where($startField, '<=', $fromDate)
                        ->where($endField, '>=', $toDate);
                });
        });
    }

    /**
     * Filter records where a date column matches the given date.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @return Builder<TModel>
     */
    public static function applyDateFilter(
        Builder $model,
        string $field,
        ?string $date,
    ): Builder {
        if (empty($date)) {
            return $model;
        }

        return $model->whereDate($field, Carbon::parse($date)->toDateString());
    }

    /**
     * Filter a parent model by a date range on a related model.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @return Builder<TModel>
     */
    public static function applyRelationDateRangeFilter(
        Builder $model,
        string $relation,
        string $field,
        ?string $fromDate,
        ?string $toDate,
    ): Builder {
        if (empty($fromDate) || empty($toDate)) {
            return $model;
        }

        return $model->whereHas($relation, function ($query) use ($field, $fromDate, $toDate): void {
            $query->whereBetween($field, [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
            ]);
        });
    }

    /**
     * Filter records that are "active" (null date or in the future).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $model
     * @return Builder<TModel>
     */
    public static function applyActiveDateFilter(Builder $model, string $field): Builder
    {
        return $model->where(function ($query) use ($field): void {
            $query->whereNull($field)
                ->orWhere($field, '>', now());
        });
    }

    /**
     * Apply grouped "LIKE" conditions for the given fields.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, literal-string>  $fields
     */
    private static function applySearchConditions(Builder $query, string $searchText, array $fields): void
    {
        $searchText = mb_strtolower($searchText);

        $query->where(function ($query) use ($searchText, $fields): void {
            foreach ($fields as $field) {
                $query->orWhereRaw("LOWER({$field}) LIKE ?", ["%{$searchText}%"]);
            }
        });
    }

    /**
     * Remove null and empty-string values from a filter map.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function withoutEmptyValues(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
