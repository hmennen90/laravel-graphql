<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;

/** A GraphQL union type. */
final class UnionType extends Type implements NamedType, OutputType, CompositeType, AbstractType
{
    /** @var array<int, ObjectType>|null */
    private ?array $resolvedTypes = null;

    private readonly ?Closure $resolveType;

    /**
     * @param Closure():array<int, ObjectType>|array<int, ObjectType> $typesConfig
     */
    public function __construct(
        private readonly string $name,
        private readonly Closure|array $typesConfig,
        private readonly ?string $description = null,
        ?callable $resolveType = null,
    ) {
        $this->resolveType = $resolveType !== null ? Closure::fromCallable($resolveType) : null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<int, ObjectType>
     */
    public function types(): array
    {
        if ($this->resolvedTypes !== null) {
            return $this->resolvedTypes;
        }

        $raw = $this->typesConfig instanceof Closure ? ($this->typesConfig)() : $this->typesConfig;

        return $this->resolvedTypes = array_values($raw);
    }

    public function hasType(string $name): bool
    {
        return array_any($this->types(), fn($type) => $type->name() === $name);
    }

    public function resolveType(mixed $value, mixed $context): ObjectType|string|null
    {
        if ($this->resolveType === null) {
            return null;
        }

        $resolved = ($this->resolveType)($value, $context);

        return $resolved instanceof ObjectType || is_string($resolved) ? $resolved : null;
    }

    public function toString(): string
    {
        return $this->name;
    }
}
