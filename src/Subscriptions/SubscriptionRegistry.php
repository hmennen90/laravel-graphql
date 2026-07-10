<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

/**
 * Records subscription field → broadcast channel mappings. This is the seam the
 * full WebSocket transport (a later milestone) builds on; v1 ships the registry
 * so application code can associate subscriptions with Laravel broadcast channels.
 */
final class SubscriptionRegistry
{
    /** @var array<string, string> */
    private array $channels = [];

    public function register(string $subscriptionField, string $channel): void
    {
        $this->channels[$subscriptionField] = $channel;
    }

    public function channelFor(string $subscriptionField): ?string
    {
        return $this->channels[$subscriptionField] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->channels;
    }
}
