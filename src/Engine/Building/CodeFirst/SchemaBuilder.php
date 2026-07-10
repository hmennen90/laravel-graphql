<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst;

use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Directive;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** A small fluent helper for assembling a code-first {@see Schema}. */
final class SchemaBuilder
{
    private ?ObjectType $query = null;

    private ?ObjectType $mutation = null;

    private ?ObjectType $subscription = null;

    /** @var array<int, Type&NamedType> */
    private array $types = [];

    /** @var array<int, Directive> */
    private array $directives = [];

    public function query(ObjectType $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function mutation(ObjectType $mutation): self
    {
        $this->mutation = $mutation;

        return $this;
    }

    public function subscription(ObjectType $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function addType(Type&NamedType $type): self
    {
        $this->types[] = $type;

        return $this;
    }

    public function addDirective(Directive $directive): self
    {
        $this->directives[] = $directive;

        return $this;
    }

    public function config(): SchemaConfig
    {
        return new SchemaConfig(
            query: $this->query,
            mutation: $this->mutation,
            subscription: $this->subscription,
            types: $this->types,
            directives: $this->directives,
        );
    }

    public function build(): Schema
    {
        return new Schema($this->config());
    }
}
