<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Http\Controllers;

use Hmennen90\GraphQL\Subscriptions\Sse\EchoSseWriter;
use Hmennen90\GraphQL\Subscriptions\Sse\EventStream;
use Hmennen90\GraphQL\Subscriptions\Sse\SseProtocolHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * graphql-sse endpoint. A query/mutation streams one `next` frame then `complete`;
 * a subscription streams `next` frames from the configured {@see EventStream} until
 * the client disconnects. Works over plain HTTP — no WebSocket server required.
 */
final readonly class SubscriptionSseController
{
    public function __construct(
        private SseProtocolHandler $handler,
        private EventStream $events,
    ) {
    }

    public function handle(Request $request): StreamedResponse
    {
        $query = is_string($request->input('query')) ? $request->input('query') : '';
        $operationName = is_string($request->input('operationName')) ? $request->input('operationName') : null;

        $variables = [];
        $rawVariables = $request->input('variables');
        if (is_array($rawVariables)) {
            foreach ($rawVariables as $key => $value) {
                $variables[(string) $key] = $value;
            }
        }

        return new StreamedResponse(
            function () use ($query, $variables, $operationName): void {
                $writer = new EchoSseWriter();
                $topic = $this->handler->start($writer, $query, $variables, $operationName);
                if ($topic === null) {
                    return; // query/mutation already fully written
                }

                foreach ($this->events->listen($topic) as $event) {
                    if (connection_aborted() === 1) {
                        break;
                    }
                    $this->handler->deliver($writer, $query, $variables, $operationName, $event);
                }
                $writer->complete();
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ],
        );
    }
}
