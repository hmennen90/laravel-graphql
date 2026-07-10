<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

/** Default no-op publisher used when no out-of-process transport is configured. */
final class NullEventPublisher implements EventPublisher
{
    public function publish(string $topic, mixed $event): void
    {
    }
}
