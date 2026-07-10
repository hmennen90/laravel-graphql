<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;

/** A leaf output type (scalar or enum) that coerces values to/from PHP. */
interface LeafType
{
    /** Coerce an internal PHP value to its serialized output representation. */
    public function serialize(mixed $value): mixed;

    /** Coerce an input (variable) value to its internal representation. */
    public function parseValue(mixed $value): mixed;

    /**
     * Coerce a literal AST value to its internal representation.
     *
     * @param  array<string, mixed>  $variables
     */
    public function parseLiteral(ValueNode $node, array $variables): mixed;
}
