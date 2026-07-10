<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Executor;

use Throwable;

/**
 * A minimal synchronous promise. Callbacks are queued and drained explicitly by
 * the {@see Executor}; combined with {@see Deferred} this enables batched
 * (DataLoader-style) resolution without real async I/O.
 */
final class SyncPromise
{
    public const int PENDING = 0;

    public const int FULFILLED = 1;

    public const int REJECTED = 2;

    public int $state = self::PENDING;

    public mixed $value = null;

    /** @var array<int, array{0: ?callable, 1: ?callable, 2: SyncPromise}> */
    private array $handlers = [];

    /** @var array<int, callable> */
    public static array $queue = [];

    public static function resolved(mixed $value): self
    {
        $promise = new self();
        $promise->fulfill($value);

        return $promise;
    }

    public static function rejected(Throwable $error): self
    {
        $promise = new self();
        $promise->reject($error);

        return $promise;
    }

    /**
     * Run $callback and wrap its outcome (thrown → rejected) in a promise.
     */
    public static function try(callable $callback): self
    {
        try {
            return self::resolved($callback());
        } catch (Throwable $e) {
            return self::rejected($e);
        }
    }

    public function fulfill(mixed $value): void
    {
        if ($this->state !== self::PENDING) {
            return;
        }
        if ($value instanceof self) {
            $value->then(
                function (mixed $v): void {
                    $this->fulfill($v);
                },
                function (Throwable $e): void {
                    $this->reject($e);
                },
            );

            return;
        }
        $this->state = self::FULFILLED;
        $this->value = $value;
        $this->schedule();
    }

    public function reject(Throwable $error): void
    {
        if ($this->state !== self::PENDING) {
            return;
        }
        $this->state = self::REJECTED;
        $this->value = $error;
        $this->schedule();
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): self
    {
        $result = new self();
        $this->handlers[] = [$onFulfilled, $onRejected, $result];
        if ($this->state !== self::PENDING) {
            $this->schedule();
        }

        return $result;
    }

    /**
     * Resolve when every promise settles, yielding their values in order.
     *
     * @param  array<int, SyncPromise>  $promises
     */
    public static function all(array $promises): self
    {
        $result = new self();
        $remaining = count($promises);

        if ($remaining === 0) {
            $result->fulfill([]);

            return $result;
        }

        // Pre-fill in index order so results come out ordered without an O(n log n)
        // ksort once every item has settled (items may settle out of order).
        $values = array_fill(0, $remaining, null);

        foreach ($promises as $index => $promise) {
            $promise->then(
                function (mixed $value) use (&$values, &$remaining, $index, $result): void {
                    $values[$index] = $value;
                    if (--$remaining === 0) {
                        $result->fulfill($values);
                    }
                },
                function (Throwable $e) use ($result): void {
                    $result->reject($e);
                },
            );
        }

        return $result;
    }

    private function schedule(): void
    {
        foreach ($this->handlers as $handler) {
            self::$queue[] = function () use ($handler): void {
                $this->dispatch($handler);
            };
        }
        $this->handlers = [];
    }

    /**
     * @param  array{0: ?callable, 1: ?callable, 2: SyncPromise}  $handler
     */
    private function dispatch(array $handler): void
    {
        [$onFulfilled, $onRejected, $result] = $handler;

        try {
            if ($this->state === self::FULFILLED) {
                $result->fulfill($onFulfilled !== null ? $onFulfilled($this->value) : $this->value);
            } elseif ($onRejected !== null) {
                $result->fulfill($onRejected($this->value));
            } elseif ($this->value instanceof Throwable) {
                $result->reject($this->value);
            }
        } catch (Throwable $e) {
            $result->reject($e);
        }
    }

    public static function runQueue(): void
    {
        // Walk the queue with a moving index instead of array_shift(): shifting
        // re-indexes the whole array on every call, making a drain of N microtasks
        // O(N²). Callbacks may append more tasks (contiguous keys), which this loop
        // picks up before resetting.
        $index = 0;
        while (array_key_exists($index, self::$queue)) {
            $callback = self::$queue[$index];
            $index++;
            $callback();
        }
        self::$queue = [];
    }
}
