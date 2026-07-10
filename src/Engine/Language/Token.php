<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

/**
 * A single lexical token with its source span and (for value-bearing kinds) its value.
 */
final readonly class Token implements \Stringable
{
    public function __construct(
        public TokenKind $kind,
        public int $start,
        public int $end,
        public int $line,
        public int $column,
        public ?string $value = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->value !== null
            ? $this->kind->value.' "'.$this->value.'"'
            : $this->kind->value;
    }
}
