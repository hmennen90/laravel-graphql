<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Scalars;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\BooleanValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;

/** The built-in `Boolean` scalar. */
final class BooleanType extends ScalarType
{
    protected string $name = 'Boolean';

    protected ?string $description = 'The `Boolean` scalar type represents `true` or `false`.';

    public function serialize(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }

        throw new CoercionError(sprintf('Boolean cannot represent value: %s', get_debug_type($value)));
    }

    public function parseValue(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new CoercionError(sprintf('Boolean cannot represent a non-boolean value: %s', get_debug_type($value)));
        }

        return $value;
    }

    public function parseLiteral(ValueNode $node, array $variables): bool
    {
        if (! $node instanceof BooleanValueNode) {
            throw new CoercionError('Boolean cannot represent a non-boolean value.');
        }

        return $node->value;
    }
}
