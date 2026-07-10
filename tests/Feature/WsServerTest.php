<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Subscriptions\EventPublisher;
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;
use Hmennen90\GraphQL\Tests\TestCase;

final class SpyEventPublisher implements EventPublisher
{
    /** @var array<int, array{topic: string, event: mixed}> */
    public array $published = [];

    public function publish(string $topic, mixed $event): void
    {
        $this->published[] = ['topic' => $topic, 'event' => $event];
    }
}

final class WsServerTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.subscriptions.enabled', true);
        $app['config']->set('cache.default', 'array');
    }

    public function test_broadcast_publishes_to_the_event_bus(): void
    {
        $spy = new SpyEventPublisher();
        $this->app->instance(EventPublisher::class, $spy);

        $this->app->make(SubscriptionManager::class)->broadcast('postAdded', ['id' => '5']);

        $this->assertSame([['topic' => 'postAdded', 'event' => ['id' => '5']]], $spy->published);
    }

    public function test_serve_command_without_a_driver_fails_gracefully(): void
    {
        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            $this->markTestSkipped('Swoole is available; the server would boot and block.');
        }

        $this->artisan('graphql:subscriptions:serve')
            ->expectsOutputToContain('No graphql-ws server driver is available')
            ->assertFailed();
    }
}
