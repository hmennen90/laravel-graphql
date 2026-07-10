<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Throwable;

/**
 * A value that is fetched later. Resolvers return a Deferred to defer work (e.g.
 * a batched DataLoader load); the executor runs all queued fetchers together
 * after each synchronous pass.
 */
final class Deferred
{
    public readonly SyncPromise $promise;

    /** @var array<int, array{0: SyncPromise, 1: callable}> */
    public static array $queue = [];

    public function __construct(callable $fetch)
    {
        $this->promise = new SyncPromise();
        self::$queue[] = [$this->promise, $fetch];
    }

    /** Run all queued fetchers; returns false when the queue was empty. */
    public static function runQueue(): bool
    {
        if (self::$queue === []) {
            return false;
        }

        $batch = self::$queue;
        self::$queue = [];

        foreach ($batch as [$promise, $fetch]) {
            try {
                $promise->fulfill($fetch());
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        }

        return true;
    }
}
