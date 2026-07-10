<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;
use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\FloatValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;

/**
 * A user-defined scalar. Coercion callbacks are optional; when omitted the value
 * passes through unchanged (literals are read from their AST node).
 */
final class CustomScalarType extends ScalarType
{
    private readonly ?Closure $serializeFn;

    private readonly ?Closure $parseValueFn;

    private readonly ?Closure $parseLiteralFn;

    public function __construct(
        string $name,
        ?callable $serialize = null,
        ?callable $parseValue = null,
        ?callable $parseLiteral = null,
        ?string $description = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->serializeFn = $serialize !== null ? Closure::fromCallable($serialize) : null;
        $this->parseValueFn = $parseValue !== null ? Closure::fromCallable($parseValue) : null;
        $this->parseLiteralFn = $parseLiteral !== null ? Closure::fromCallable($parseLiteral) : null;
    }

    public function serialize(mixed $value): mixed
    {
        return $this->serializeFn !== null ? ($this->serializeFn)($value) : $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $this->parseValueFn !== null ? ($this->parseValueFn)($value) : $value;
    }

    public function parseLiteral(ValueNode $node, array $variables): mixed
    {
        if ($this->parseLiteralFn !== null) {
            return ($this->parseLiteralFn)($node, $variables);
        }

        return match (true) {
            $node instanceof IntValueNode => (int) $node->value,
            $node instanceof FloatValueNode => (float) $node->value,
            $node instanceof StringValueNode => $node->value,
            $node instanceof BooleanValueNode => $node->value,
            default => null,
        };
    }
}
