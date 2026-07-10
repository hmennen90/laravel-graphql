<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class EnumTypeDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     * @param  array<int, EnumValueDefinitionNode>  $values
     */
    public function __construct(
        public readonly ?string $description,
        public readonly string $name,
        public readonly array $directives,
        public readonly array $values,
    ) {
    }
}
