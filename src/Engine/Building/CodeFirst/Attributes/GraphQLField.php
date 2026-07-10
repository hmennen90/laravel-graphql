<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes;

use Attribute;

/** Marks a public method as a GraphQL field; the method is its resolver. */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class GraphQLField
{
    /**
     * @param  array<string, string>  $args  argument name => input-type expression (e.g. ['limit' => 'Int', 'q' => 'String!'])
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public ?string $description = null,
        public array $args = [],
    ) {
    }
}
