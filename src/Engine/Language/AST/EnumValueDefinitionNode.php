<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class EnumValueDefinitionNode extends Node
{
    /**
     * @param  array<int, DirectiveNode>  $directives
     */
    public function __construct(
        public readonly ?string $description,
        public readonly string $name,
        public readonly array $directives,
    ) {
    }
}
