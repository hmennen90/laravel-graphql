<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\ResponseBuilder;

/**
 * graphql-sse (Server-Sent Events) transport core, transport-agnostic and testable.
 * SSE works over plain HTTP — no WebSocket server or extension required.
 *
 * A query/mutation completes immediately (one `next`, then `complete`). A subscription
 * returns its topic; the caller streams events into {@see deliver()} as they arrive
 * (e.g. from Redis pub/sub) and calls `complete()` on disconnect.
 */
final readonly class SseProtocolHandler
{
    public function __construct(
        private GraphQL $graphql,
        private ResponseBuilder $responses,
    ) {
    }

    /**
     * Begin an operation. Returns the subscription topic to stream, or null when the
     * operation was a query/mutation (already fully written to the stream).
     *
     * @param  array<string, mixed>  $variables
     */
    public function start(SseWriter $writer, string $query, array $variables = [], ?string $operationName = null): ?string
    {
        $analysis = $this->graphql->analyze($query, $operationName);

        if (! $analysis->isSubscription) {
            $result = $this->graphql->execute($query, $variables, $operationName);
            $writer->next($this->responses->build($result));
            $writer->complete();

            return null;
        }

        return $analysis->rootField;
    }

    /**
     * Deliver a subscription event by re-executing the operation with the event as the
     * root value and writing a `next` frame.
     *
     * @param  array<string, mixed>  $variables
     */
    public function deliver(SseWriter $writer, string $query, array $variables, ?string $operationName, mixed $event): void
    {
        $result = $this->graphql->execute($query, $variables, $operationName, null, $event);
        $writer->next($this->responses->build($result));
    }
}
