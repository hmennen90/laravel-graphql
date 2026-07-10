<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Subscriptions;

use Hmennen90\GraphQL\Subscriptions\Sse\RedisEventStream;
use PHPUnit\Framework\TestCase;

/** Fake stream feeding pre-queued raw payloads instead of blocking on Redis. */
final class FakeRedisEventStream extends RedisEventStream
{
    /** @param list<string|null> $messages */
    public function __construct(private array $messages)
    {
        parent::__construct();
    }

    #[\Override]
    protected function pop(string $key): ?string
    {
        return array_shift($this->messages);
    }
}

final class RedisEventStreamTest extends TestCase
{
    public function test_decodes_and_yields_queued_events(): void
    {
        $stream = new FakeRedisEventStream([
            json_encode(['id' => '1'], JSON_THROW_ON_ERROR),
            null, // a timeout in between is skipped
            json_encode(['id' => '2'], JSON_THROW_ON_ERROR),
        ]);

        $received = [];
        foreach ($stream->listen('postAdded') as $event) {
            $received[] = $event;
            if (count($received) >= 2) {
                break;
            }
        }

        $this->assertSame([['id' => '1'], ['id' => '2']], $received);
    }
}
