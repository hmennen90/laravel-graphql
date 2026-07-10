<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes;

use Attribute;

/** Marks a public method as a GraphQL field; the method is its resolver. */
#[Attribute(Attribute::TARGET_METHOD)]
final class GraphQLField
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {
    }
}
