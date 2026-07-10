<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;

/** A GraphQL object type: a set of named fields with resolvers. */
final class ObjectType extends Type implements NamedType, OutputType, CompositeType
{
    use ResolvesFields;

    /** @var array<int, InterfaceType>|null */
    private ?array $resolvedInterfaces = null;

    private readonly ?Closure $isTypeOf;

    /**
     * @param  Closure(): array<int|string, FieldDefinition>|array<int|string, FieldDefinition>  $fields
     * @param Closure():array<int, InterfaceType>|array<int, InterfaceType> $interfacesConfig
     */
    public function __construct(
        private readonly string $name,
        Closure|array $fields,
        private Closure|array $interfacesConfig = [],
        private readonly ?string $description = null,
        ?callable $isTypeOf = null,
    ) {
        $this->fieldsConfig = $fields;
        $this->isTypeOf = $isTypeOf !== null ? Closure::fromCallable($isTypeOf) : null;
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

    public function implementsInterface(string $name): bool
    {
        return array_any($this->interfaces(), fn($interface) => $interface->name() === $name);
    }

    public function isTypeOf(mixed $value, mixed $context): ?bool
    {
        if ($this->isTypeOf === null) {
            return null;
        }

        return (bool) ($this->isTypeOf)($value, $context);
    }

    public function toString(): string
    {
        return $this->name;
    }
}
