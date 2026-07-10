<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A field of an {@see InputObjectType}. */
final class InputObjectField
{
    public function __construct(
        private readonly string $name,
        private readonly Type&InputType $type,
        private readonly bool $hasDefaultValue = false,
        private readonly mixed $defaultValue = null,
        private readonly ?string $description = null,
        private readonly ?string $deprecationReason = null,
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
