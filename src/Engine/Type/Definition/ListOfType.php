<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A list wrapper. */
final class ListOfType extends Type implements OutputType, InputType, WrappingType
{
    public function __construct(private readonly Type $wrappedType)
    {
    }

    public function wrappedType(): Type
    {
        return $this->wrappedType;
    }

    public function toString(): string
    {
        return '['.$this->wrappedType->toString().']';
    }
}
