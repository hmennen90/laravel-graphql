<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;

/** A GraphQL interface type. */
final class InterfaceType extends Type implements NamedType, OutputType, CompositeType, AbstractType
{
    use ResolvesFields;

    /** @var array<int, InterfaceType>|null */
    private ?array $resolvedInterfaces = null;

    private readonly ?Closure $resolveType;

    /**
     * @param  Closure(): array<int|string, FieldDefinition>|array<int|string, FieldDefinition>  $fields
     * @param Closure():array<int, InterfaceType>|array<int, InterfaceType> $interfacesConfig
     */
    public function __construct(
        private readonly string $name,
        Closure|array $fields,
        private Closure|array $interfacesConfig = [],
        private readonly ?string $description = null,
        ?callable $resolveType = null,
    ) {
        $this->fieldsConfig = $fields;
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
     * @return array<int, InterfaceType>
     */
    public function interfaces(): array
    {
        if ($this->resolvedInterfaces !== null) {
            return $this->resolvedInterfaces;
        }

        $raw = $this->interfacesConfig instanceof Closure ? ($this->interfacesConfig)() : $this->interfacesConfig;

        return $this->resolvedInterfaces = array_values($raw);
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
