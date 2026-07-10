<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class ListTypeNode extends Node implements TypeNode
{
    public function __construct(public readonly TypeNode $type)
    {
    }
}
