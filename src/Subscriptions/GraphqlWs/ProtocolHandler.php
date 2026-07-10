<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\GraphqlWs;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;

/**
 * Implements the `graphql-ws` protocol (connection_init/ack, subscribe, next,
 * complete, ping/pong), transport-agnostic. Each active subscription is keyed by
 * its client id and topic (the root subscription field); {@see publish()} maps an
 * event to every matching subscriber by re-executing its stored operation.
 */
final class ProtocolHandler
{
    private bool $initialized = false;

    /** @var array<string, array{conn: Connection, topic: string, query: string, variables: array<string, mixed>, operationName: ?string}> */
    private array $subscriptions = [];

    public function __construct(
        private readonly GraphQL $graphql,
        private readonly ResponseBuilder $responses,
    ) {
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function onMessage(Connection $connection, array $message): void
    {
        $type = is_string($message['type'] ?? null) ? $message['type'] : '';

        match ($type) {
            'connection_init' => $this->handleInit($connection),
            'ping' => $connection->send(['type' => 'pong']),
            'pong' => null,
            'subscribe' => $this->handleSubscribe($connection, $message),
            'complete' => $this->handleComplete($message),
            default => $connection->close(4400, sprintf('Invalid message type "%s".', $type)),
        };
    }

    public function onClose(Connection $connection): void
    {
        foreach ($this->subscriptions as $id => $subscription) {
            if ($subscription['conn']->id() === $connection->id()) {
                unset($this->subscriptions[$id]);
            }
        }
    }

    /** Deliver an event to every subscriber on the given topic. */
    public function publish(string $topic, mixed $event): void
    {
        foreach ($this->subscriptions as $id => $subscription) {
            if ($subscription['topic'] !== $topic) {
                continue;
            }

            $result = $this->graphql->execute(
                $subscription['query'],
                $subscription['variables'],
                $subscription['operationName'],
                null,
                $event,
            );

            $subscription['conn']->send([
                'type' => 'next',
                'id' => $id,
                'payload' => $this->responses->build($result),
            ]);
        }
    }

    private function handleInit(Connection $connection): void
    {
        if ($this->initialized) {
            $connection->close(4429, 'Too many initialisation requests.');

            return;
        }
        $this->initialized = true;
        $connection->send(['type' => 'connection_ack']);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleSubscribe(Connection $connection, array $message): void
    {
        if (! $this->initialized) {
            $connection->close(4401, 'Unauthorized.');

            return;
        }

        $id = is_string($message['id'] ?? null) ? $message['id'] : '';
        if ($id === '' || isset($this->subscriptions[$id])) {
            $connection->close(4409, sprintf('Subscriber for "%s" already exists.', $id));

            return;
        }

        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $query = is_string($payload['query'] ?? null) ? $payload['query'] : '';
        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $operationName = is_string($payload['operationName'] ?? null) ? $payload['operationName'] : null;

        /** @var array<string, mixed> $variables */
        $analysis = $this->graphql->analyze($query, $operationName);

        if (! $analysis->isSubscription) {
            // Queries and mutations complete immediately over the socket.
            $result = $this->graphql->execute($query, $variables, $operationName);
            $connection->send(['type' => 'next', 'id' => $id, 'payload' => $this->responses->build($result)]);
            $connection->send(['type' => 'complete', 'id' => $id]);

            return;
        }

        $this->subscriptions[$id] = [
            'conn' => $connection,
            'topic' => $analysis->rootField ?? '',
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operationName,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleComplete(array $message): void
    {
        $id = is_string($message['id'] ?? null) ? $message['id'] : '';
        unset($this->subscriptions[$id]);
    }
}
