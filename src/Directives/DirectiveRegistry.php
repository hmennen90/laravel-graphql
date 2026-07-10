<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;

/**
 * Central registry of build-time schema directives (name → implementation).
 * The manager passes these to the SDL builder so `@all`, `@find`, `@can`, … are
 * available in schema files without manual wiring.
 */
final class DirectiveRegistry
{
    /** @var array<string, SchemaDirective> */
    private array $directives = [];

    public function register(string $name, SchemaDirective $directive): void
    {
        $this->directives[$name] = $directive;
    }

    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    /**
     * @return array<string, SchemaDirective>
     */
    public function all(): array
    {
        return $this->directives;
    }
}
