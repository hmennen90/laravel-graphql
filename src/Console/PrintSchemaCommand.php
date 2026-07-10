<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Engine\Schema\SchemaPrinter;
use Hmennen90\GraphQL\GraphQL;
use Illuminate\Console\Command;

/** Prints the configured schema as SDL. */
final class PrintSchemaCommand extends Command
{
    protected $signature = 'graphql:print';

    protected $description = 'Print the configured GraphQL schema as SDL.';

    public function handle(GraphQL $graphql): int
    {
        $this->line(SchemaPrinter::print($graphql->schema()));

        return self::SUCCESS;
    }
}
