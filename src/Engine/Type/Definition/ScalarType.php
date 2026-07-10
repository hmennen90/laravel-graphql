<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** Base class for scalar types (both built-in and custom). */
abstract class ScalarType extends Type implements NamedType, OutputType, InputType, LeafType
{
    protected string $name = '';

    protected ?string $description = null;

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function toString(): string
    {
        return $this->name;
    }
}
