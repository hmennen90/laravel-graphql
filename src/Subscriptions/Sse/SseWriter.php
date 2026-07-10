<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

/**
 * Sink for `text/event-stream` frames, abstracted from the HTTP transport so the
 * protocol handler is testable. Frames follow the graphql-sse "distinct connections"
 * mode: `next` events carry an execution result, `complete` ends the stream.
 */
interface SseWriter
{
    /**
     * @param  array<string, mixed>  $payload  a GraphQL execution result envelope
     */
    public function next(array $payload): void;

    public function complete(): void;
}
