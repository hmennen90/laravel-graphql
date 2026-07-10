<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Support\Relay\Relay;

/**
 * `@globalId` — decodes a Relay global identifier argument (`base64("Type:id")`)
 * down to its raw id, so resolvers receive the underlying database key.
 */
final readonly class GlobalIdDirective implements ArgumentDirective
{
    #[\Override]
    public function applyToArgument(Argument $argument, DirectiveNode $node, SchemaBuildContext $context): Closure
    {
        return static function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            return Relay::fromGlobalId($value)[1] ?? $value;
        };
    }
}
