<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

/**
 * A GraphQL source document: the raw body plus a name used in error messages.
 */
final class Source
{
    public function __construct(
        private readonly string $body,
        private readonly string $name = 'GraphQL',
    ) {
    }

    public function body(): string
    {
        return $this->body;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function length(): int
    {
        return strlen($this->body);
    }

    /**
     * Translate a byte offset into a 1-based line/column location.
     */
    public function getLocation(int $position): SourceLocation
    {
        $line = 1;
        $column = 1;

        $limit = min($position, strlen($this->body));
        for ($i = 0; $i < $limit; $i++) {
            if ($this->body[$i] === "\n") {
                $line++;
                $column = 1;
            } else {
                $column++;
            }
        }

        return new SourceLocation($line, $column);
    }
}
