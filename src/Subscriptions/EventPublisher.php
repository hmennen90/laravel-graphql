<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

/**
 * Publishes subscription events onto a shared bus so out-of-process transports
 * (e.g. a graphql-ws WebSocket server) receive them.
 */
interface EventPublisher
{
    public function publish(string $topic, mixed $event): void;
}
