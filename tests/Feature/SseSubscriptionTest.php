<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\Sse\SseProtocolHandler;
use Hmennen90\GraphQL\Subscriptions\Sse\SseWriter;
use Hmennen90\GraphQL\Tests\TestCase;

final class RecordingSseWriter implements SseWriter
{
    /** @var list<array<string, mixed>> */
    public array $nexts = [];

    public bool $completed = false;

    public function next(array $payload): void
    {
        $this->nexts[] = $payload;
    }

    public function complete(): void
    {
        $this->completed = true;
    }
}

/** graphql-sse transport core: query/mutation complete immediately, subscriptions stream. */
final class SseSubscriptionTest extends TestCase
{
    private function handler(): SseProtocolHandler
    {
        return new SseProtocolHandler($this->app->make(GraphQL::class), $this->app->make(ResponseBuilder::class));
    }

    public function test_query_streams_one_next_then_completes(): void
    {
        $writer = new RecordingSseWriter();
        $topic = $this->handler()->start($writer, '{ hello }');

        $this->assertNull($topic); // not a subscription
        $this->assertCount(1, $writer->nexts);
        $this->assertSame('world', $writer->nexts[0]['data']['hello']);
        $this->assertTrue($writer->completed);
    }

    public function test_subscription_returns_topic_without_emitting(): void
    {
        $writer = new RecordingSseWriter();
        $topic = $this->handler()->start($writer, 'subscription { postAdded { id } }');

        $this->assertSame('postAdded', $topic);
        $this->assertSame([], $writer->nexts);
        $this->assertFalse($writer->completed);
    }

    public function test_deliver_writes_a_next_frame_for_an_event(): void
    {
        $writer = new RecordingSseWriter();
        $this->handler()->deliver($writer, 'subscription { postAdded { id title } }', [], null, ['id' => '5', 'title' => 'Hi']);

        $this->assertCount(1, $writer->nexts);
        $this->assertSame(['id' => '5', 'title' => 'Hi'], $writer->nexts[0]['data']['postAdded']);
    }
}
