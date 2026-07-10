<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** `@notIn` — where column NOT IN (values). */
final readonly class NotInDirective extends ComparatorDirective
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[\Override]
    protected function constrain(Builder $builder, string $column, mixed $value): Builder
    {
        return $builder->whereNotIn($column, is_array($value) ? $value : [$value]);
    }
}
