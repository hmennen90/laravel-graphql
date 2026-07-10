<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class SchemaDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     * @param  array<int, OperationTypeDefinitionNode>  $operationTypes
     */
    public function __construct(
        public readonly ?string $description,
        public readonly array $directives,
        public readonly array $operationTypes,
    ) {
    }
}
