<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Exceptions;

use Hmennen90\GraphQL\Engine\Error\GraphQLError;

/** A client-safe authorization failure surfaced in the GraphQL error response. */
final class AuthorizationError extends GraphQLError
{
}
