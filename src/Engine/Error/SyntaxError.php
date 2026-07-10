<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Error;

use Hmennen90\GraphQL\Engine\Language\Source;

/**
 * Raised by the lexer/parser when the document is not syntactically valid.
 */
final class SyntaxError extends GraphQLError
{
    public static function at(Source $source, int $position, string $description): self
    {
        return new self(
            "Syntax Error: {$description}",
            source: $source,
            positions: [$position],
        );
    }
}
