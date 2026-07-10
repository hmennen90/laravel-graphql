<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\GraphQL;
use Illuminate\Console\Command;

/** Compiles the SDL schema's AST to the cache file for faster boots. */
final class CacheSchemaCommand extends Command
{
    protected $signature = 'graphql:cache';

    protected $description = 'Cache the parsed GraphQL schema (SDL schemas only).';

    public function handle(GraphQL $graphql): int
    {
        $path = $graphql->cacheSchema();

        if ($path === null) {
            $this->warn('Nothing cached: schema caching applies to SDL schemas (graphql.schema.sdl_path) with a configured cache path.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Cached the schema AST to %s.', $path));
        $this->line('Set graphql.schema.cache.enabled = true (GRAPHQL_SCHEMA_CACHE) to use it.');

        return self::SUCCESS;
    }
}
