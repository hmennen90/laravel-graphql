<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language;

/**
 * A 1-based line/column position within a {@see Source}.
 */
final class SourceLocation
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    /**
     * @return array{line: int, column: int}
     */
    public function toArray(): array
    {
        return ['line' => $this->line, 'column' => $this->column];
    }
}
