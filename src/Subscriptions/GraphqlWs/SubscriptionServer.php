<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\GraphqlWs;

/** A runnable graphql-ws WebSocket server. Concrete drivers are transport-specific. */
interface SubscriptionServer
{
    public function run(string $host, int $port): void;
}
