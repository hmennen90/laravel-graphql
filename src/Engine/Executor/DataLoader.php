<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Closure;

/**
 * Batches and caches loads by key to eliminate N+1 queries. `load()` returns a
 * {@see SyncPromise}; all keys requested during one execution pass are fetched
 * together via the batch callback when the executor drains its deferred queue.
 *
 * @template TKey of string|int
 */
final class DataLoader
{
    private readonly Closure $batchLoad;

    /** @var array<string|int, true> */
    private array $queued = [];

    /** @var array<string|int, mixed> */
    private array $results = [];

    /**
     * @param  callable(array<int, string|int>): array<int, mixed>  $batchLoad
     */
    public function __construct(callable $batchLoad)
    {
        $this->batchLoad = Closure::fromCallable($batchLoad);
    }

    public function load(string|int $key): SyncPromise
    {
        if (! array_key_exists($key, $this->results)) {
            $this->queued[$key] = true;
        }

        return (new Deferred(function () use ($key): mixed {
            $this->dispatch();

            return $this->results[$key] ?? null;
        }))->promise;
    }

    /**
     * @param  array<int, string|int>  $keys
     * @return array<int, SyncPromise>
     */
    public function loadMany(array $keys): array
    {
        return array_map($this->load(...), $keys);
    }

    private function dispatch(): void
    {
        if ($this->queued === []) {
            return;
        }

        $keys = array_keys($this->queued);
        $this->queued = [];

        $values = ($this->batchLoad)($keys);
        foreach ($keys as $index => $key) {
            $this->results[$key] = $values[$index] ?? null;
        }
    }
}
