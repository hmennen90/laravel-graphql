<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building;

use Closure;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/**
 * Handed to build-time directives so they can rewrite a field, register
 * additional generated types (paginators, filter inputs, …) and look up existing
 * types, with knowledge of the field's position in the schema.
 */
final readonly class SchemaBuildContext
{
    /**
     * @param  Closure(Type&NamedType): void  $register
     * @param  Closure(string): ((Type&NamedType)|null)  $lookup
     */
    public function __construct(
        private Closure $register,
        private Closure $lookup,
        public string $parentTypeName,
        public string $fieldName,
    ) {
    }

    public function registerType(Type&NamedType $type): void
    {
        ($this->register)($type);
    }

    public function getType(string $name): (Type&NamedType)|null
    {
        return ($this->lookup)($name);
    }
}
