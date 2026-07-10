<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class VariableDefinitionNode extends Node
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly VariableNode $variable,
        public readonly TypeNode $type,
        public readonly ?ValueNode $defaultValue,
        public readonly array $directives,
    ) {
    }
}
