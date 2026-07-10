<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A field / directive argument definition. */
final readonly class Argument
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

    public static function make(
        string $name,
        Type&InputType $type,
        ?string $description = null,
        ?string $deprecationReason = null,
    ): self {
        return new self($name, $type, false, null, $description, $deprecationReason);
    }

    public static function withDefault(
        string $name,
        Type&InputType $type,
        mixed $defaultValue,
        ?string $description = null,
        ?string $deprecationReason = null,
    ): self {
        return new self($name, $type, true, $defaultValue, $description, $deprecationReason);
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

    public function deprecationReason(): ?string
    {
        return $this->deprecationReason;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecationReason !== null;
    }
}
