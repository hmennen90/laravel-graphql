<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

/**
 * Optional side-channel for {@see SchemaPrinter} to emit directive annotations it
 * cannot derive from the built schema (which discards SDL directives at build
 * time). Used by Apollo Federation to print `@link`, `@key`, `@shareable`, … onto
 * the subgraph SDL.
 */
interface SchemaAnnotations
{
    /** A block prepended before the type definitions (e.g. `extend schema @link(...)`), or ''. */
    public function header(): string;

    /** Directives appended to a type header, e.g. ` @key(fields: "id")`, or ''. */
    public function typeAnnotations(string $type): string;

    /** Directives appended to a field line, e.g. ` @shareable`, or ''. */
    public function fieldAnnotations(string $type, string $field): string;
}
