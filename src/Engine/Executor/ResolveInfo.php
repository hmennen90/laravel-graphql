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
final readonly class ResolveInfo
{
    /**
     * @param  array<int, FieldNode>  $fieldNodes
     * @param  array<int, string|int>  $path
     * @param  array<string, mixed>  $variableValues
     */
    public function __construct(
        public string $fieldName,
        public array $fieldNodes,
        public Type&OutputType $returnType,
        public CompositeType $parentType,
        public array $path,
        public Schema $schema,
        public array $variableValues,
        public OperationDefinitionNode $operation,
        public mixed $rootValue,
    ) {
    }
}
