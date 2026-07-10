<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\SchemaFirst;

/**
 * Maps SDL type/field names to resolver callables and abstract-type resolvers.
 */
final class ResolverMap
{
    /**
     * @param  array<string, array<string, callable>>  $fieldResolvers
     * @param  array<string, callable>  $typeResolvers
     */
    public function __construct(
        private readonly array $fieldResolvers = [],
        private readonly array $typeResolvers = [],
    ) {
    }

    public function resolver(string $type, string $field): ?callable
    {
        return $this->fieldResolvers[$type][$field] ?? null;
    }

    public function typeResolver(string $type): ?callable
    {
        return $this->typeResolvers[$type] ?? null;
    }
}
