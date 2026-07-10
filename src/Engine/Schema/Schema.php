<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

use Hmennen90\GraphQL\Engine\Type\Definition\AbstractType;
use Hmennen90\GraphQL\Engine\Type\Definition\Directive;
use Hmennen90\GraphQL\Engine\Type\Definition\Directives;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Type\Definition\UnionType;

/**
 * The single internal representation of a GraphQL schema. Both the code-first and
 * schema-first builders produce a {@see SchemaConfig}, which this class turns into
 * one queryable schema for the validator and executor.
 */
final class Schema
{
    private readonly TypeRegistry $registry;

    /** @var array<string, Directive> */
    private readonly array $directives;

    public function __construct(private readonly SchemaConfig $config)
    {
        $registry = new TypeRegistry();
        foreach (Type::builtInScalars() as $scalar) {
            $registry->collect($scalar);
        }
        $registry->collect($config->query);
        $registry->collect($config->mutation);
        $registry->collect($config->subscription);
        foreach ($config->types as $type) {
            $registry->collect($type);
        }
        $this->registry = $registry;

        $directives = $config->directives !== [] ? $config->directives : Directives::all();
        $keyed = [];
        foreach ($directives as $directive) {
            $keyed[$directive->name()] = $directive;
        }
        $this->directives = $keyed;
    }

    public function getQueryType(): ?ObjectType
    {
        return $this->config->query;
    }

    public function getMutationType(): ?ObjectType
    {
        return $this->config->mutation;
    }

    public function getSubscriptionType(): ?ObjectType
    {
        return $this->config->subscription;
    }

    public function getType(string $name): (Type&NamedType)|null
    {
        return $this->registry->get($name);
    }

    /**
     * @return array<string, Type&NamedType>
     */
    public function getTypeMap(): array
    {
        return $this->registry->all();
    }

    /**
     * The concrete object types a given abstract (interface/union) type may resolve to.
     *
     * @return array<int, ObjectType>
     */
    public function getPossibleTypes(AbstractType $abstract): array
    {
        if ($abstract instanceof UnionType) {
            return $abstract->types();
        }

        if ($abstract instanceof InterfaceType) {
            $possible = [];
            foreach ($this->registry->all() as $type) {
                if ($type instanceof ObjectType && $type->implementsInterface($abstract->name())) {
                    $possible[] = $type;
                }
            }

            return $possible;
        }

        return [];
    }

    public function isPossibleType(AbstractType $abstract, ObjectType $possible): bool
    {
        foreach ($this->getPossibleTypes($abstract) as $type) {
            if ($type->name() === $possible->name()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that this schema is well-formed.
     *
     * @return array<int, \Hmennen90\GraphQL\Engine\Error\GraphQLError>
     */
    public function validate(): array
    {
        return (new SchemaValidator())->validate($this);
    }

    public function getDirective(string $name): ?Directive
    {
        return $this->directives[$name] ?? null;
    }

    public function getDirectiveMiddleware(string $name): ?\Hmennen90\GraphQL\Engine\Executor\DirectiveMiddleware
    {
        $middleware = $this->config->directiveMiddleware[$name] ?? null;

        return $middleware instanceof \Hmennen90\GraphQL\Engine\Executor\DirectiveMiddleware ? $middleware : null;
    }

    /**
     * @return array<string, Directive>
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }
}
