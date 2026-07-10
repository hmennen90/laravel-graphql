<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Scalars;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;

/** The built-in `String` scalar. */
final class StringType extends ScalarType
{
    protected string $name = 'String';

    protected ?string $description = 'The `String` scalar type represents textual data.';

    public function serialize(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new CoercionError(sprintf('String cannot represent value: %s', get_debug_type($value)));
    }

    public function parseValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new CoercionError(sprintf('String cannot represent a non-string value: %s', get_debug_type($value)));
        }

        return $value;
    }

    public function parseLiteral(ValueNode $node, array $variables): string
    {
        if (! $node instanceof StringValueNode) {
            throw new CoercionError('String cannot represent a non-string value.');
        }

        return $node->value;
    }
}
