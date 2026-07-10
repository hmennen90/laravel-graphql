<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

use Illuminate\Contracts\Redis\Factory as Redis;

/**
 * Publishes subscription events to a Redis pub/sub channel that a graphql-ws
 * server subscribes to and fans out to connected clients.
 */
final class RedisEventPublisher implements EventPublisher
{
    public const string CHANNEL = 'graphql:subscriptions';

    public function __construct(
        private readonly Redis $redis,
        private readonly ?string $connection = null,
    ) {
    }

    public function publish(string $topic, mixed $event): void
    {
        $payload = json_encode(['topic' => $topic, 'event' => $event]);
        if ($payload === false) {
            return;
        }

        $this->redis->connection($this->connection)->publish(self::CHANNEL, $payload);
    }
}
