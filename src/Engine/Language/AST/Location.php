<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

use Hmennen90\GraphQL\Engine\Language\Source;
use Hmennen90\GraphQL\Engine\Language\Token;

/**
 * The span of source a node was parsed from.
 */
final readonly class Location
{
    public function __construct(
        public int $start,
        public int $end,
        public Source $source,
        public ?Token $startToken = null,
        public ?Token $endToken = null,
    ) {
    }
}
