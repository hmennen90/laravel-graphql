<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\Sse;

/** Writes graphql-sse frames to the PHP output buffer and flushes immediately. */
final class EchoSseWriter implements SseWriter
{
    public function next(array $payload): void
    {
        $this->emit('next', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function complete(): void
    {
        $this->emit('complete', '');
    }

    private function emit(string $event, string $data): void
    {
        echo 'event: '.$event."\n".'data: '.$data."\n\n";

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
}
