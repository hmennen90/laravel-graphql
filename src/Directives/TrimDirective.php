<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;

/** `@trim` — strips leading and trailing whitespace from a string argument. */
final readonly class TrimDirective implements ArgumentDirective
{
    #[\Override]
    public function applyToArgument(Argument $argument, DirectiveNode $node, SchemaBuildContext $context): Closure
    {
        return static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;
    }
}
