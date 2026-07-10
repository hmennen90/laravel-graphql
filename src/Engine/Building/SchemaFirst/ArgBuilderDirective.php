<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Closure;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

/**
 * Build-time behaviour for a directive on a field *argument* that contributes a
 * query constraint (e.g. `field(name: String @eq)`, `@scope`, `@limit`). It returns
 * a closure applied to the query builder with the argument's runtime value; the
 * builder is typed `mixed` to keep the engine framework-agnostic (the Eloquent layer
 * passes an Eloquent builder).
 */
interface ArgBuilderDirective
{
    /**
     * @return Closure(mixed, mixed): mixed  (builder, argument value) => builder
     */
    public function toBuilder(DirectiveNode $node, string $argName): Closure;
}
