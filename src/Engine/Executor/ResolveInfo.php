<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\CompositeType;
use Hmennen90\GraphQL\Engine\Type\Definition\OutputType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

/** Context passed to every field resolver. */
final class ResolveInfo
{
    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     * @param  array<string, mixed>  $variableValues
     */
    public function __construct(
        public readonly string $fieldName,
        public readonly array $fieldNodes,
        public readonly Type&OutputType $returnType,
        public readonly CompositeType $parentType,
        public readonly array $path,
        public readonly Schema $schema,
        public readonly array $variableValues,
        public readonly OperationDefinitionNode $operation,
        public readonly mixed $rootValue,
    ) {
    }
}
