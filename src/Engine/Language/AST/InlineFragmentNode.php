<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class InlineFragmentNode extends Node implements SelectionNode
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly ?NamedTypeNode $typeCondition,
        public readonly array $directives,
        public readonly SelectionSetNode $selectionSet,
    ) {
    }
}
