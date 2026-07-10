<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class ObjectTypeExtensionNode extends Node implements DefinitionNode
{
    /**
     * @param  array<int, NamedTypeNode>  $interfaces
     * @param  array<int, DirectiveNode>  $directives
     * @param  array<int, FieldDefinitionNode>  $fields
     */
    public function __construct(
        public readonly string $name,
        public readonly array $interfaces,
        public readonly array $directives,
        public readonly array $fields,
    ) {
    }
}
