<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class SelectionSetNode extends Node
{
    /**
     * @param  array<int, SelectionNode>  $selections
     */
    public function __construct(public readonly array $selections)
    {
    }
}
