<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class DocumentNode extends Node
{
    /**
     * @param  array<int, DefinitionNode>  $definitions
     */
    public function __construct(public readonly array $definitions)
    {
    }
}
