<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class DirectiveDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    /**
     * @param  array<int, InputValueDefinitionNode>  $arguments
     * @param  array<int, string>  $locations
     */
    public function __construct(
        public readonly ?string $description,
        public readonly string $name,
        public readonly array $arguments,
        public readonly bool $repeatable,
        public readonly array $locations,
    ) {
    }
}
