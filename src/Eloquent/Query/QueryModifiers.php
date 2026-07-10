<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Eloquent\Query;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies `where`/`orderBy` GraphQL arguments (as produced by the @whereConditions
 * and @orderBy directives) to an Eloquent query builder. Column names are already
 * constrained to an allow-list by the generated enum types.
 */
final class QueryModifiers
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<array-key, mixed>  $args
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function apply(Builder $query, array $args): Builder
    {
        $where = $args['where'] ?? null;
        if (is_array($where)) {
            foreach ($where as $condition) {
                if (is_array($condition)) {
                    self::applyWhere($query, $condition);
                }
            }
        }

        $orderBy = $args['orderBy'] ?? null;
        if (is_array($orderBy)) {
            foreach ($orderBy as $clause) {
                if (! is_array($clause) || ! is_string($clause['column'] ?? null)) {
                    continue;
                }
                $order = $clause['order'] ?? 'ASC';
                $descending = is_string($order) && strtoupper($order) === 'DESC';
                $query->orderBy($clause['column'], $descending ? 'desc' : 'asc');
            }
        }

        return $query;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<array-key, mixed>  $condition
     */
    private static function applyWhere(Builder $query, array $condition): void
    {
        $column = $condition['column'] ?? null;
        if (! is_string($column)) {
            return;
        }
        $operatorValue = $condition['operator'] ?? 'EQ';
        $operator = is_string($operatorValue) ? strtoupper($operatorValue) : 'EQ';
        $value = $condition['value'] ?? null;

        match ($operator) {
            'IN' => $query->whereIn($column, is_array($value) ? $value : [$value]),
            'NOT_IN' => $query->whereNotIn($column, is_array($value) ? $value : [$value]),
            'LIKE' => $query->where($column, 'like', $value),
            'NEQ' => $query->where($column, '!=', $value),
            'GT' => $query->where($column, '>', $value),
            'GTE' => $query->where($column, '>=', $value),
            'LT' => $query->where($column, '<', $value),
            'LTE' => $query->where($column, '<=', $value),
            default => $query->where($column, '=', $value),
        };
    }
}
