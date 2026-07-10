<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Resolves Automatic Persisted Queries (Apollo APQ): registers a query by its
 * sha256 hash and serves it on subsequent hash-only requests.
 */
final readonly class PersistedQueryResolver
{
    private const string PREFIX = 'graphql:pq:';

    public function __construct(
        private Cache $cache,
        private Config $config,
    ) {
    }

    /**
     * @param  array<string, mixed>  $extensions
     */
    public function resolve(string $query, array $extensions): string
    {
        $persisted = $extensions['persistedQuery'] ?? null;
        if (! is_array($persisted) || $this->config->get('graphql.persisted_queries.enabled') !== true) {
            return $query;
        }

        $hash = $persisted['sha256Hash'] ?? null;
        if (! is_string($hash)) {
            return $query;
        }

        if ($query !== '') {
            if (! hash_equals($hash, hash('sha256', $query))) {
                throw new PersistedQueryException('Provided sha256Hash does not match the query.', 'PERSISTED_QUERY_HASH_MISMATCH');
            }
            $this->cache->forever(self::PREFIX.$hash, $query);

            return $query;
        }

        $stored = $this->cache->get(self::PREFIX.$hash);
        if (! is_string($stored)) {
            throw new PersistedQueryException('PersistedQueryNotFound', 'PERSISTED_QUERY_NOT_FOUND');
        }

        return $stored;
    }
}
