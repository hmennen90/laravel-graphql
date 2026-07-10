<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class ObjectValueNode extends Node implements ValueNode
{
    /**
     * @param  array<int, ObjectFieldNode>  $fields
     */
    public function __construct(public readonly array $fields)
    {
    }
}
