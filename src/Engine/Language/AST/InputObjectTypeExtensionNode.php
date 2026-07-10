<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class InputObjectTypeExtensionNode extends Node implements DefinitionNode
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     * @param  array<int, InputValueDefinitionNode>  $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly array $directives,
        public readonly array $fields,
    ) {
    }
}
