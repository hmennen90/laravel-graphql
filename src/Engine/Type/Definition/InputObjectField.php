<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A field of an {@see InputObjectType}. */
final readonly class InputObjectField
{
    public function __construct(
        private string $name,
        private Type&InputType $type,
        private bool $hasDefaultValue = false,
        private mixed $defaultValue = null,
        private ?string $description = null,
        private ?string $deprecationReason = null,
    ) {
    }

    public static function make(string $name, Type&InputType $type, ?string $description = null): self
    {
        return new self($name, $type, false, null, $description);
    }

    public function deprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type&InputType
    {
        return $this->type;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
