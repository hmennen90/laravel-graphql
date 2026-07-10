<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

/**
 * Base class for every AST node. The {@see Location} is attached by the parser.
 */
abstract class Node
{
    public ?Location $loc = null;
}
