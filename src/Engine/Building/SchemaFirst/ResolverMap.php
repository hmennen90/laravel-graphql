<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

use Closure;

/**
 * Maps SDL type/field names to resolver callables and abstract-type resolvers.
 * When no explicit field resolver is registered, an optional `$fallback` closure
 * is consulted — the Laravel layer uses it for convention-based resolution of
 * root query/mutation fields, keeping this class framework-agnostic.
 */
final readonly class ResolverMap
{
    /** @var Closure(string, string): ?callable */
    private Closure $fallback;

    /**
     * @param  array<string, array<string, callable>>  $fieldResolvers
     * @param  array<string, callable>  $typeResolvers
     * @param  (callable(string, string): ?callable)|null  $fallback
     */
    public function __construct(
        private array $fieldResolvers = [],
        private array $typeResolvers = [],
        ?callable $fallback = null,
    ) {
        $this->fallback = $fallback !== null
            ? Closure::fromCallable($fallback)
            : static fn (string $type, string $field): ?callable => null;
    }

    public function resolver(string $type, string $field): ?callable
    {
        return $this->fieldResolvers[$type][$field] ?? ($this->fallback)($type, $field);
    }

    public function typeResolver(string $type): ?callable
    {
        return $this->typeResolvers[$type] ?? null;
    }
}
