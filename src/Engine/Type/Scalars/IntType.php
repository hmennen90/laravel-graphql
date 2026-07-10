<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Scalars;

use Hmennen90\GraphQL\Engine\Error\CoercionError;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ValueNode;
use Hmennen90\GraphQL\Engine\Type\Definition\ScalarType;

/** The built-in `Int` scalar: a signed 32-bit integer. */
final class IntType extends ScalarType
{
    private const int MAX = 2147483647;

    private const int MIN = -2147483648;

    protected string $name = 'Int';

    protected ?string $description = 'The `Int` scalar type represents non-fractional signed whole numeric values.';

    public function serialize(mixed $value): int
    {
        return $this->coerce($value);
    }

    public function parseValue(mixed $value): int
    {
        return $this->coerce($value);
    }

    public function parseLiteral(ValueNode $node, array $variables): int
    {
        if (! $node instanceof IntValueNode) {
            throw new CoercionError('Int cannot represent non-integer value.');
        }

        return $this->coerce($node->value);
    }

    private function coerce(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            $int = $value;
        } elseif (is_float($value) && floor($value) === $value && ! is_infinite($value)) {
            $int = (int) $value;
        } elseif (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
            $int = (int) $value;
        } else {
            throw new CoercionError(sprintf('Int cannot represent non-integer value: %s', self::describe($value)));
        }

        if ($int > self::MAX || $int < self::MIN) {
            throw new CoercionError(sprintf('Int cannot represent non 32-bit signed integer value: %d', $int));
        }

        return $int;
    }

    private static function describe(mixed $value): string
    {
        return is_scalar($value) ? var_export($value, true) : get_debug_type($value);
    }
}
