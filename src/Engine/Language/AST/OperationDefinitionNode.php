<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class OperationDefinitionNode extends Node implements DefinitionNode
{
    /**
     * @param  array<int, VariableDefinitionNode>  $variableDefinitions
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly OperationType $operation,
        public readonly ?string $name,
        public readonly array $variableDefinitions,
        public readonly array $directives,
        public readonly SelectionSetNode $selectionSet,
    ) {
    }
}
