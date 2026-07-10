<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Type\Definition;

/** A GraphQL directive definition. */
final class Directive
{
    /** @var array<string, Argument> */
    private readonly array $args;

    /**
     * @param  array<int, string>  $locations
     * @param  array<int|string, Argument>  $args
     */
    public function __construct(
        private readonly string $name,
        private readonly array $locations,
        array $args = [],
        private readonly bool $repeatable = false,
        private readonly ?string $description = null,
    ) {
        $keyed = [];
        foreach ($args as $arg) {
            $keyed[$arg->getName()] = $arg;
        }
        $this->args = $keyed;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function locations(): array
    {
        return $this->locations;
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

    public function isRepeatable(): bool
    {
        return $this->repeatable;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
