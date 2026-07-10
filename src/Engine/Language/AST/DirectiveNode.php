<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class DirectiveNode extends Node
{
    /**
     * @param  array<int, ArgumentNode>  $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
