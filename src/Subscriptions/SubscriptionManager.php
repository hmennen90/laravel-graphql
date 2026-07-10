<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\Events\SubscriptionBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Registers subscription operations and, on {@see broadcast()}, re-executes each
 * subscriber's stored operation against the event payload and pushes the result
 * to that subscriber's channel via Laravel broadcasting.
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly SubscriptionStore $store,
        private readonly GraphQL $graphql,
        private readonly ResponseBuilder $responses,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function register(string $topic, string $query, array $variables, ?string $operationName): Subscriber
    {
        $subscriber = new Subscriber($this->newChannel(), $topic, $query, $variables, $operationName);
        $this->store->store($subscriber);

        return $subscriber;
    }

    public function broadcast(string $topic, mixed $root): void
    {
        foreach ($this->store->subscribersByTopic($topic) as $subscriber) {
            $result = $this->graphql->execute(
                $subscriber->query,
                $subscriber->variables,
                $subscriber->operationName,
                null,
                $root,
            );

            $this->events->dispatch(new SubscriptionBroadcast(
                $subscriber->channel,
                $this->responses->build($result),
            ));
        }
    }

    public function forget(string $channel): void
    {
        $this->store->delete($channel);
    }

    private function newChannel(): string
    {
        return 'graphql.'.bin2hex(random_bytes(12));
    }
}
