<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Http\PersistedQueryResolver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;

/** Removes the cached schema AST and flushes persisted-query (APQ) entries. */
final class ClearCacheCommand extends Command
{
    protected $signature = 'graphql:clear';

    protected $description = 'Clear the GraphQL schema cache and persisted-query entries.';

    public function handle(Config $config, CacheFactory $cache): int
    {
        $cacheConfig = $config->get('graphql.schema.cache');
        $path = is_array($cacheConfig) && is_string($cacheConfig['path'] ?? null) ? $cacheConfig['path'] : null;

        if ($path !== null && is_file($path)) {
            unlink($path);
            $this->info(sprintf('Removed schema cache: %s.', $path));
        } else {
            $this->line('No schema cache file to remove.');
        }

        $store = $cache->store();
        if (method_exists($store, 'tags')) {
            try {
                $store->tags([PersistedQueryResolver::TAG])->flush();
                $this->info('Flushed persisted-query (APQ) cache.');
            } catch (Throwable) {
                $this->line('Persisted-query cache store is not taggable — run cache:clear to flush it.');
            }
        } else {
            $this->line('Persisted-query cache store is not taggable — run cache:clear to flush it.');
        }

        return self::SUCCESS;
    }
}
