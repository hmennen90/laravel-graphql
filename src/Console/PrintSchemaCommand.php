<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Engine\Schema\SchemaPrinter;
use Hmennen90\GraphQL\GraphQL;
use Illuminate\Console\Command;

/** Prints the configured schema as SDL (optionally to a file for IDE/tooling). */
final class PrintSchemaCommand extends Command
{
    protected $signature = 'graphql:print {--write= : Write the SDL to this file (defaults to base_path/schema.graphql) instead of stdout}';

    protected $description = 'Print the configured GraphQL schema as SDL.';

    public function handle(GraphQL $graphql): int
    {
        $sdl = SchemaPrinter::print($graphql->schema());

        if ($this->input->hasParameterOption('--write')) {
            $target = $this->option('write');
            $path = is_string($target) && $target !== '' ? $target : $this->laravel->basePath('schema.graphql');
            file_put_contents($path, $sdl."\n");
            $this->info(sprintf('Wrote schema SDL to %s.', $path));

            return self::SUCCESS;
        }

        $this->line($sdl);

        return self::SUCCESS;
    }
}
