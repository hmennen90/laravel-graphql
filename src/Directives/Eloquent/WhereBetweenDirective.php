<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** `@whereBetween` — where column BETWEEN [min, max]. */
final readonly class WhereBetweenDirective extends ComparatorDirective
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[\Override]
    protected function constrain(Builder $builder, string $column, mixed $value): Builder
    {
        return is_array($value) && count($value) === 2 ? $builder->whereBetween($column, [$value[0], $value[1]]) : $builder;
    }
}
