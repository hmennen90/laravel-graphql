<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\Connection;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\ProtocolHandler;
use Hmennen90\GraphQL\Tests\TestCase;

final class FakeConnection implements Connection
{
    /** @var array<int, array<string, mixed>> */
    public array $sent = [];

    public ?int $closedCode = null;

    public function __construct(private readonly string $id = 'conn-1')
    {
    }

    public function send(array $message): void
    {
        $this->sent[] = $message;
    }

    public function close(int $code, string $reason): void
    {
        $this->closedCode = $code;
    }

    public function id(): string
    {
        return $this->id;
    }
}

final class GraphqlWsProtocolTest extends TestCase
{
    private function handler(): ProtocolHandler
    {
        return new ProtocolHandler($this->app->make(GraphQL::class), $this->app->make(ResponseBuilder::class));
    }

    public function test_connection_init_is_acknowledged(): void
    {
        $conn = new FakeConnection();
        $this->handler()->onMessage($conn, ['type' => 'connection_init']);

        $this->assertSame([['type' => 'connection_ack']], $conn->sent);
    }

    public function test_subscribe_before_init_is_rejected(): void
    {
        $conn = new FakeConnection();
        $this->handler()->onMessage($conn, ['type' => 'subscribe', 'id' => '1', 'payload' => ['query' => 'subscription { postAdded { id } }']]);

        $this->assertSame(4401, $conn->closedCode);
    }

    public function test_subscribe_then_publish_streams_next(): void
    {
        $conn = new FakeConnection();
        $handler = $this->handler();

        $handler->onMessage($conn, ['type' => 'connection_init']);
        $handler->onMessage($conn, [
            'type' => 'subscribe',
            'id' => 'sub-1',
            'payload' => ['query' => 'subscription { postAdded { id title } }'],
        ]);

        // no data streamed yet
        $this->assertCount(1, $conn->sent);

        $handler->publish('postAdded', ['id' => '7', 'title' => 'Hello']);

        $next = $conn->sent[1];
        $this->assertSame('next', $next['type']);
        $this->assertSame('sub-1', $next['id']);
        $this->assertSame(['id' => '7', 'title' => 'Hello'], $next['payload']['data']['postAdded']);
    }

    public function test_complete_stops_streaming(): void
    {
        $conn = new FakeConnection();
        $handler = $this->handler();

        $handler->onMessage($conn, ['type' => 'connection_init']);
        $handler->onMessage($conn, ['type' => 'subscribe', 'id' => 'sub-1', 'payload' => ['query' => 'subscription { postAdded { id } }']]);
        $handler->onMessage($conn, ['type' => 'complete', 'id' => 'sub-1']);

        $handler->publish('postAdded', ['id' => '9']);

        // only the ack was sent; publish after complete delivers nothing
        $this->assertCount(1, $conn->sent);
    }

    public function test_ping_gets_pong(): void
    {
        $conn = new FakeConnection();
        $this->handler()->onMessage($conn, ['type' => 'ping']);

        $this->assertSame([['type' => 'pong']], $conn->sent);
    }
}
