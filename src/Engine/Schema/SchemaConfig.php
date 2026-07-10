<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Schema;

use Hmennen90\GraphQL\Engine\Type\Definition\Directive;
use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** Immutable configuration used to build a {@see Schema}. */
final class SchemaConfig
{
    /**
     * @param  array<int, Type&NamedType>  $types  Additional types not reachable from the roots.
     * @param  array<int, Directive>  $directives  Custom directives; defaults to the built-ins when empty.
     * @param  array<string, \Hmennen90\GraphQL\Engine\Executor\DirectiveMiddleware>  $directiveMiddleware  Runtime handlers keyed by directive name.
     */
    public function __construct(
        public readonly ?ObjectType $query = null,
        public readonly ?ObjectType $mutation = null,
        public readonly ?ObjectType $subscription = null,
        public readonly array $types = [],
        public readonly array $directives = [],
        public readonly array $directiveMiddleware = [],
    ) {
    }
}
