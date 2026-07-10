<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Swoole;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\Connection;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\ProtocolHandler;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\SubscriptionServer;
use Hmennen90\GraphQL\Subscriptions\RedisEventPublisher;

/*
 * NOTE: This file is intentionally excluded from static analysis (phpstan.neon
 * excludePaths) because it depends on the Swoole/OpenSwoole PHP extension, which
 * is environment-specific and not installed in CI. It provides a concrete,
 * runnable graphql-ws server; the protocol itself lives in the fully-tested,
 * transport-agnostic ProtocolHandler.
 */

/**
 * A graphql-ws server backed by Swoole/OpenSwoole. Bridges Swoole WebSocket
 * frames to the {@see ProtocolHandler} and fans out Redis pub/sub events.
 */
final class SwooleSubscriptionServer implements SubscriptionServer
{
    private ProtocolHandler $handler;

    public function __construct(GraphQL $graphql, ResponseBuilder $responses)
    {
        $this->handler = new ProtocolHandler($graphql, $responses);
    }

    public function run(string $host, int $port): void
    {
        $serverClass = '\Swoole\WebSocket\Server';
        $server = new $serverClass($host, $port);

        $server->on('message', function ($server, $frame): void {
            $message = json_decode((string) $frame->data, true);
            if (is_array($message)) {
                $this->handler->onMessage(new SwooleConnection($server, (int) $frame->fd), $message);
            }
        });

        $server->on('close', function ($server, int $fd): void {
            $this->handler->onClose(new SwooleConnection($server, $fd));
        });

        // Fan out Redis pub/sub subscription events to connected clients.
        $server->on('workerStart', function () use ($server): void {
            \go(function () use ($server): void {
                $redisClass = '\Swoole\Coroutine\Redis';
                $redis = new $redisClass();
                $redis->connect('127.0.0.1', 6379);
                $redis->subscribe([RedisEventPublisher::CHANNEL]);
                while (true) {
                    $message = $redis->recv();
                    if (! is_array($message) || ($message[0] ?? null) !== 'message') {
                        continue;
                    }
                    $decoded = json_decode((string) ($message[2] ?? ''), true);
                    if (is_array($decoded) && isset($decoded['topic'])) {
                        $this->handler->publish((string) $decoded['topic'], $decoded['event'] ?? null);
                    }
                }
            });
        });

        $server->start();
    }
}

/** @internal Swoole-backed {@see Connection} adapter. */
final class SwooleConnection implements Connection
{
    public function __construct(private $server, private int $fd)
    {
    }

    public function send(array $message): void
    {
        $this->server->push($this->fd, (string) json_encode($message));
    }

    public function close(int $code, string $reason): void
    {
        $this->server->disconnect($this->fd, $code, $reason);
    }

    public function id(): string
    {
        return (string) $this->fd;
    }
}
