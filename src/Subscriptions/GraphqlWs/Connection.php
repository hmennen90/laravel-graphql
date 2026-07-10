<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Subscriptions\GraphqlWs;

/** A single client connection, abstracted from the underlying WebSocket server. */
interface Connection
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function send(array $message): void;

    public function close(int $code, string $reason): void;

    public function id(): string;
}
