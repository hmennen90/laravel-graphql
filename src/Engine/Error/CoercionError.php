<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Error;

/**
 * Raised when a value cannot be coerced to/from a scalar or enum type.
 */
final class CoercionError extends GraphQLError
{
}
