<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Language\Token;

/**
 * The span of source a node was parsed from.
 */
final class Location
{
    public function __construct(
        public readonly int $start,
        public readonly int $end,
        public readonly Source $source,
        public readonly ?Token $startToken = null,
        public readonly ?Token $endToken = null,
    ) {
    }
}
