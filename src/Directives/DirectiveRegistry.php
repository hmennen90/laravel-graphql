<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Directives;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgBuilderDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaDirective;

/**
 * Central registry of build-time directives (name → implementation). The manager
 * passes these to the SDL builder so `@all`, `@find`, `@rules`, `@trim`, … are
 * available in schema files without manual wiring. A directive is either a
 * {@see SchemaDirective} (applies to a field) or an {@see ArgumentDirective}
 * (applies to a field argument).
 */
final class DirectiveRegistry
{
    /** @var array<string, SchemaDirective|ArgumentDirective|ArgBuilderDirective> */
    private array $directives = [];

    public function register(string $name, SchemaDirective|ArgumentDirective|ArgBuilderDirective $directive): void
    {
        $this->directives[$name] = $directive;
    }

    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    /**
     * @return array<string, SchemaDirective|ArgumentDirective|ArgBuilderDirective>
     */
    public function all(): array
    {
        return $this->directives;
    }
}
