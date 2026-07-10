<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class ListValueNode extends Node implements ValueNode
{
    /**
     * @param  array<int, ValueNode>  $values
     */
    public function __construct(public readonly array $values)
    {
    }
}
