<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class FragmentDefinitionNode extends Node implements DefinitionNode
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly string $name,
        public readonly NamedTypeNode $typeCondition,
        public readonly array $directives,
        public readonly SelectionSetNode $selectionSet,
    ) {
    }
}
