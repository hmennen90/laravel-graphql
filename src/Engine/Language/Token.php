<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

/**
 * A single lexical token with its source span and (for value-bearing kinds) its value.
 */
final class Token
{
    public function __construct(
        public readonly TokenKind $kind,
        public readonly int $start,
        public readonly int $end,
        public readonly int $line,
        public readonly int $column,
        public readonly ?string $value = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->value !== null
            ? $this->kind->value.' "'.$this->value.'"'
            : $this->kind->value;
    }
}
