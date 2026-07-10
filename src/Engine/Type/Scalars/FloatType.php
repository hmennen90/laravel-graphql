<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Scalars;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\FloatValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;

/** The built-in `Float` scalar: a signed double-precision value. */
final class FloatType extends ScalarType
{
    protected string $name = 'Float';

    protected ?string $description = 'The `Float` scalar type represents signed double-precision fractional values.';

    public function serialize(mixed $value): float
    {
        return $this->coerce($value);
    }

    public function parseValue(mixed $value): float
    {
        return $this->coerce($value);
    }

    public function parseLiteral(ValueNode $node, array $variables): float
    {
        if ($node instanceof IntValueNode || $node instanceof FloatValueNode) {
            return $this->coerce($node->value);
        }

        throw new CoercionError('Float cannot represent non-numeric value.');
    }

    private function coerce(mixed $value): float
    {
        if (is_int($value)) {
            return (float) $value;
        }

        if (is_float($value)) {
            $float = $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $float = (float) $value;
        } else {
            throw new CoercionError(sprintf('Float cannot represent non-numeric value: %s', get_debug_type($value)));
        }

        if (is_infinite($float) || is_nan($float)) {
            throw new CoercionError('Float cannot represent non-finite value.');
        }

        return $float;
    }
}
