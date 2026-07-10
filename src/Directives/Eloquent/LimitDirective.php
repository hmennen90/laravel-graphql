<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives\Eloquent;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgBuilderDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Illuminate\Database\Eloquent\Builder;

/** `@limit` — caps the number of results at the argument's value. */
final readonly class LimitDirective implements ArgBuilderDirective
{
    public function toBuilder(DirectiveNode $node, string $argName): Closure
    {
        return static function (mixed $builder, mixed $value): mixed {
            if (! $builder instanceof Builder || ! is_numeric($value)) {
                return $builder;
            }

            return $builder->limit(max((int) $value, 0));
        };
    }
}
