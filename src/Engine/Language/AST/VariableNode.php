<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class VariableNode extends Node implements ValueNode
{
    public function __construct(public readonly string $name)
    {
    }
}
