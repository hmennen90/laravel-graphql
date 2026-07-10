<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** An interface or union type whose concrete object type is resolved at runtime. */
interface AbstractType
{
    /**
     * Resolve the concrete {@see ObjectType} (or its name) for a resolved value.
     */
    public function resolveType(mixed $value, mixed $context): ObjectType|string|null;
}
