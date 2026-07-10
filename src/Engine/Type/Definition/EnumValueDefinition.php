<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A single value of an {@see EnumType}. */
final readonly class EnumValueDefinition
{
    public function __construct(
        private string $name,
        private mixed $value = null,
        private ?string $description = null,
        private ?string $deprecationReason = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** The internal value; defaults to the value's name when not given. */
    public function getValue(): mixed
    {
        return $this->value ?? $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function deprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }
}
