<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class FieldNode extends Node implements SelectionNode
{
    /**
     * @param  array<int, ArgumentNode>  $arguments
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly ?string $alias,
        public readonly string $name,
        public readonly array $arguments,
        public readonly array $directives,
        public readonly ?SelectionSetNode $selectionSet,
    ) {
    }

    /** The response key: the alias if present, otherwise the field name. */
    public function responseKey(): string
    {
        return $this->alias ?? $this->name;
    }
}
