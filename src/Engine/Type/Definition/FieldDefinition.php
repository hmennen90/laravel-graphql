<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

use Closure;

/** A field on an object or interface type. */
final readonly class FieldDefinition
{
    /** @var array<string, Argument> */
    private array $args;

    private ?Closure $resolve;

    /**
     * @param  array<int|string, Argument>  $args
     * @param  array<string, mixed>  $metadata  Arbitrary schema metadata (e.g. cache hints).
     */
    public function __construct(
        private string $name,
        private Type&OutputType $type,
        ?callable $resolve = null,
        array $args = [],
        private ?string $description = null,
        private ?string $deprecationReason = null,
        private array $metadata = [],
    ) {
        $this->resolve = $resolve !== null ? Closure::fromCallable($resolve) : null;

        $keyed = [];
        foreach ($args as $arg) {
            $keyed[$arg->getName()] = $arg;
        }
        $this->args = $keyed;
    }

    /**
     * @param  array<int|string, Argument>  $args
     * @param  array<string, mixed>  $metadata
     */
    public static function make(
        string $name,
        Type&OutputType $type,
        ?callable $resolve = null,
        array $args = [],
        ?string $description = null,
        ?string $deprecationReason = null,
        array $metadata = [],
    ): self {
        return new self($name, $type, $resolve, $args, $description, $deprecationReason, $metadata);
    }

    public function metadata(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type&OutputType
    {
        return $this->type;
    }

    public function getResolver(): ?Closure
    {
        return $this->resolve;
    }

    /**
     * @return array<string, Argument>
     */
    public function args(): array
    {
        return $this->args;
    }

    public function getArg(string $name): ?Argument
    {
        return $this->args[$name] ?? null;
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
