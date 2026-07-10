<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Closure;
use Hmennen90\GraphQL\Engine\Building\SchemaBuildContext;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Illuminate\Support\Facades\Hash;

/** `@hash` — bcrypt-hashes a string argument (e.g. a password) before it is stored. */
final readonly class HashDirective implements ArgumentDirective
{
    #[\Override]
    public function applyToArgument(Argument $argument, DirectiveNode $node, SchemaBuildContext $context): Closure
    {
        return static fn (mixed $value): mixed => is_string($value) ? Hash::make($value) : $value;
    }
}
