<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;

/**
 * Build-time behaviour for a directive applied to a field *argument* (e.g.
 * `field(email: String @rules(apply: ["email"]))`). It returns a transformer
 * that runs on the argument's value before the field resolves; the transformer
 * may sanitise the value (return a new one) or reject it (throw).
 */
interface ArgumentDirective
{
    /**
     * @return Closure(mixed): mixed
     */
    public function applyToArgument(Argument $argument, DirectiveNode $node, SchemaBuildContext $context): Closure;
}
