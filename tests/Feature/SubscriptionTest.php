<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Subscriptions\Events\SubscriptionBroadcast;
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class SubscriptionTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.subscriptions.enabled', true);
        $app['config']->set('cache.default', 'array');
    }

    public function test_subscribing_returns_a_channel(): void
    {
        $response = $this->postJson('/graphql', ['query' => 'subscription { postAdded { id title } }'])->assertOk();

        $response->assertJsonPath('data', null);
        $channel = $response->json('extensions.subscription.channel');
        $this->assertIsString($channel);
        $this->assertStringStartsWith('graphql.', $channel);
    }

    public function test_subscriptions_disabled_returns_an_error(): void
    {
        config()->set('graphql.subscriptions.enabled', false);

        $this->postJson('/graphql', ['query' => 'subscription { postAdded { id title } }'])
            ->assertOk()
            ->assertJsonPath('errors.0.message', 'Subscriptions are not enabled.');
    }

    public function test_broadcast_re_executes_and_pushes_to_the_channel(): void
    {
        Event::fake([SubscriptionBroadcast::class]);

        $this->postJson('/graphql', ['query' => 'subscription { postAdded { id title } }'])->assertOk();

        /** @var SubscriptionManager $manager */
        $manager = $this->app->make(SubscriptionManager::class);
        $manager->broadcast('postAdded', ['id' => '7', 'title' => 'Hello']);

        Event::assertDispatched(SubscriptionBroadcast::class, fn(SubscriptionBroadcast $event): bool => $event->payload['data']['postAdded']['id'] === '7'
            && $event->payload['data']['postAdded']['title'] === 'Hello');
    }
}
