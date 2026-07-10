<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

final class ArgumentNode extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly ValueNode $value,
    ) {
    }
}
