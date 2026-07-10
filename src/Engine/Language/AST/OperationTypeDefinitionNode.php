<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class OperationTypeDefinitionNode extends Node
{
    public function __construct(
        public readonly OperationType $operation,
        public readonly NamedTypeNode $type,
    ) {
    }
}
