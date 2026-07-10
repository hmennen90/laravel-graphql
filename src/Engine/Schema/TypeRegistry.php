<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

use Hmennen90\GraphQL\Engine\Type\Definition\InputObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;
use Hmennen90\GraphQL\Engine\Type\Definition\WrappingType;

/**
 * Collects every named type reachable from the schema roots into a name→type map,
 * following lazy field closures and guarding against cyclic graphs.
 */
final class TypeRegistry
{
    /** @var array<string, Type&NamedType> */
    private array $types = [];

    public function collect(?Type $type): void
    {
        if ($type === null) {
            return;
        }

        while ($type instanceof WrappingType) {
            $type = $type->wrappedType();
        }

        if (! $type instanceof NamedType) {
            return;
        }

        $name = $type->name();
        if (isset($this->types[$name])) {
            return;
        }

        $this->types[$name] = $type;

        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            foreach ($type->interfaces() as $interface) {
                $this->collect($interface);
            }
            foreach ($type->fields() as $field) {
                $this->collect($field->getType());
                foreach ($field->args() as $arg) {
                    $this->collect($arg->getType());
                }
            }
        } elseif ($type instanceof UnionType) {
            foreach ($type->types() as $member) {
                $this->collect($member);
            }
        } elseif ($type instanceof InputObjectType) {
            foreach ($type->fields() as $field) {
                $this->collect($field->getType());
            }
        }
    }

    public function get(string $name): (Type&NamedType)|null
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return array<string, Type&NamedType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
