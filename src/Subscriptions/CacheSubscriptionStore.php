<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions;

use Illuminate\Contracts\Cache\Repository;

/** A subscription store backed by Laravel's cache. */
final readonly class CacheSubscriptionStore implements SubscriptionStore
{
    private const string TOPIC_PREFIX = 'graphql:sub:topic:';

    private const string CHANNEL_PREFIX = 'graphql:sub:channel:';

    public function __construct(private Repository $cache)
    {
    }

    public function store(Subscriber $subscriber): void
    {
        $this->cache->forever(self::CHANNEL_PREFIX.$subscriber->channel, $subscriber->toArray());

        $channels = $this->channelsForTopic($subscriber->topic);
        if (! in_array($subscriber->channel, $channels, true)) {
            $channels[] = $subscriber->channel;
            $this->cache->forever(self::TOPIC_PREFIX.$subscriber->topic, $channels);
        }
    }

    public function subscribersByTopic(string $topic): array
    {
        $subscribers = [];
        foreach ($this->channelsForTopic($topic) as $channel) {
            $data = $this->cache->get(self::CHANNEL_PREFIX.$channel);
            if (is_array($data)) {
                $subscribers[] = Subscriber::fromArray($data);
            }
        }

        return $subscribers;
    }

    public function delete(string $channel): void
    {
        $data = $this->cache->get(self::CHANNEL_PREFIX.$channel);
        $this->cache->forget(self::CHANNEL_PREFIX.$channel);

        if (is_array($data) && isset($data['topic']) && is_string($data['topic'])) {
            $channels = array_values(array_filter(
                $this->channelsForTopic($data['topic']),
                static fn (string $c): bool => $c !== $channel,
            ));
            $this->cache->forever(self::TOPIC_PREFIX.$data['topic'], $channels);
        }
    }

    /**
     * @return array<int, string>
     */
    private function channelsForTopic(string $topic): array
    {
        $channels = $this->cache->get(self::TOPIC_PREFIX.$topic, []);

        return is_array($channels) ? array_values(array_filter($channels, is_string(...))) : [];
    }
}
