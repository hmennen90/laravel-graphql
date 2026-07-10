<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** `@in` — where column IN (values). */
final readonly class InDirective extends ComparatorDirective
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[\Override]
    protected function constrain(Builder $builder, string $column, mixed $value): Builder
    {
        return $builder->whereIn($column, is_array($value) ? $value : [$value]);
    }
}
