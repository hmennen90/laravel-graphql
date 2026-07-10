<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes;

use Attribute;

/** Marks a PHP class as a GraphQL object type for the attribute builder. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class GraphQLType
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
