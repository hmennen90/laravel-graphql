<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Closure;
use Hmennen90\GraphQL\Directives\ReadsArguments;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgBuilderDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for single-argument where directives (`@eq`, `@neq`, `@in`, …). The column
 * defaults to the argument name and can be overridden with `key:`.
 */
abstract readonly class ComparatorDirective implements ArgBuilderDirective
{
    use ReadsArguments;

    public function toBuilder(DirectiveNode $node, string $argName): Closure
    {
        $column = $this->stringArg($node, 'key') ?? $argName;

        return fn (mixed $builder, mixed $value): mixed => $builder instanceof Builder
            ? $this->constrain($builder, $column, $value)
            : $builder;
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    abstract protected function constrain(Builder $builder, string $column, mixed $value): Builder;
}
