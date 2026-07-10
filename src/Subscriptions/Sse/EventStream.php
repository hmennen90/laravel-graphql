<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

/**
 * Source of live subscription events for the SSE transport. In production a
 * Redis-backed implementation blocks on pub/sub and yields events for the topic;
 * the default {@see NullEventStream} yields nothing (SSE then serves immediate
 * query/mutation operations only). Kept as an interface so the SSE controller is
 * testable without infrastructure.
 */
interface EventStream
{
    /**
     * @return iterable<mixed>  events published to the topic, as they arrive
     */
    public function listen(string $topic): iterable;
}
