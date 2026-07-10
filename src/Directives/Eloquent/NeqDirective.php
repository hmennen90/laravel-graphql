<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** `@neq` — where column != value. */
final readonly class NeqDirective extends ComparatorDirective
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[\Override]
    protected function constrain(Builder $builder, string $column, mixed $value): Builder
    {
        return $builder->where($column, '!=', $value);
    }
}
