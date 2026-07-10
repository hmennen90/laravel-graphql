<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Engine\Type\Definition\NamedType;
use Hmennen90\GraphQL\GraphQL;
use Illuminate\Console\Command;

/** Prints the configured schema's type names (a lightweight schema overview). */
final class PrintSchemaCommand extends Command
{
    protected $signature = 'graphql:print';

    protected $description = 'Print the configured GraphQL schema type map.';

    public function handle(GraphQL $graphql): int
    {
        $schema = $graphql->schema();

        $names = array_map(
            static fn (NamedType $type): string => $type->name(),
            array_values($schema->getTypeMap()),
        );
        sort($names);

        foreach ($names as $name) {
            $this->line($name);
        }

        return self::SUCCESS;
    }
}
