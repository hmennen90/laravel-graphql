<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

/** Default event source: yields nothing (no live subscription streaming). */
final class NullEventStream implements EventStream
{
    public function listen(string $topic): iterable
    {
        return [];
    }
}
