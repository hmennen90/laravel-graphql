<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed {@see EventStream} for SSE live subscriptions. Blocks on a per-topic
 * Redis list (`BLPOP`) and yields decoded events as they arrive; the loop ends when
 * the HTTP client disconnects. Publish events with
 * `Redis::connection($c)->rpush("graphql:sse:$topic", json_encode($event))`.
 *
 * Requires a Redis connection (phpredis or predis). Bind it in the service container:
 *   $this->app->bind(EventStream::class, fn () => new RedisEventStream('default'));
 */
class RedisEventStream implements EventStream
{
    public function __construct(
        private readonly string $connection = 'default',
        private readonly int $timeout = 15,
    ) {
    }

    public function listen(string $topic): iterable
    {
        $key = 'graphql:sse:'.$topic;

        while (connection_aborted() !== 1) {
            $raw = $this->pop($key);
            if ($raw === null) {
                continue; // BLPOP timed out — re-block until an event or disconnect
            }

            yield json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    /** Block for the next raw message on the list, or null on timeout. Overridable for tests. */
    protected function pop(string $key): ?string
    {
        $result = Redis::connection($this->connection)->command('blpop', [[$key], $this->timeout]);

        return is_array($result) && isset($result[1]) && is_string($result[1]) ? $result[1] : null;
    }
}
