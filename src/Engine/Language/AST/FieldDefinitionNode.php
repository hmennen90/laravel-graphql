<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class FieldDefinitionNode extends Node
{
    /**
     * @param  array<int, InputValueDefinitionNode>  $arguments
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly ?string $description,
        public readonly string $name,
        public readonly array $arguments,
        public readonly TypeNode $type,
        public readonly array $directives,
    ) {
    }
}
