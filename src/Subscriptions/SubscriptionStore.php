<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

/** Persists subscribers so broadcasts can re-execute their operations later. */
interface SubscriptionStore
{
    public function store(Subscriber $subscriber): void;

    /**
     * @return array<int, Subscriber>
     */
    public function subscribersByTopic(string $topic): array;

    public function delete(string $channel): void;
}
