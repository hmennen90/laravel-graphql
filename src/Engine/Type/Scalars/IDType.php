<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Scalars;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;

/** The built-in `ID` scalar: serialized as a string, accepts ints and strings. */
final class IDType extends ScalarType
{
    protected string $name = 'ID';

    protected ?string $description = 'The `ID` scalar type represents a unique identifier, serialized as a String.';

    public function serialize(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new CoercionError(sprintf('ID cannot represent value: %s', get_debug_type($value)));
    }

    public function parseValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new CoercionError(sprintf('ID cannot represent value: %s', get_debug_type($value)));
    }

    public function parseLiteral(ValueNode $node, array $variables): string
    {
        if ($node instanceof StringValueNode) {
            return $node->value;
        }

        if ($node instanceof IntValueNode) {
            return $node->value;
        }

        throw new CoercionError('ID cannot represent a non-string and non-integer value.');
    }
}
