<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** `@whereNull` — when the argument is true, `column IS NULL`; when false, `IS NOT NULL`. */
final readonly class WhereNullDirective extends ComparatorDirective
{
    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    #[\Override]
    protected function constrain(Builder $builder, string $column, mixed $value): Builder
    {
        return (bool) $value ? $builder->whereNull($column) : $builder->whereNotNull($column);
    }
}
