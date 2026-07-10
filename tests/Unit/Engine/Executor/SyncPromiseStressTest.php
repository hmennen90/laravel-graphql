<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Executor;

use Hmennen90\GraphQL\Engine\Executor\DataLoader;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Executor\SyncPromise;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Hardening for the bespoke promise/deferred machinery: ordering guarantees, error
 * propagation, and linear-time drain of large microtask graphs (regression for the
 * O(N²) array_shift() drain that was fixed).
 */
final class SyncPromiseStressTest extends TestCase
{
    protected function tearDown(): void
    {
        SyncPromise::$queue = [];
        parent::tearDown();
    }

    public function test_all_preserves_index_order_regardless_of_settle_order(): void
    {
        $a = new SyncPromise();
        $b = new SyncPromise();
        $c = new SyncPromise();

        $captured = null;
        SyncPromise::all([$a, $b, $c])->then(function (array $values) use (&$captured): void {
            $captured = $values;
        });

        // Settle out of order.
        $c->fulfill('c');
        $a->fulfill('a');
        $b->fulfill('b');
        SyncPromise::runQueue();

        $this->assertSame(['a', 'b', 'c'], $captured);
    }

    public function test_deep_then_chain_completes_correctly(): void
    {
        $promise = SyncPromise::resolved(0);
        for ($i = 0; $i < 10_000; $i++) {
            $promise = $promise->then(static fn (int $value): int => $value + 1);
        }
        SyncPromise::runQueue();

        $this->assertSame(SyncPromise::FULFILLED, $promise->state);
        $this->assertSame(10_000, $promise->value);
    }

    public function test_rejection_is_recoverable_and_propagates(): void
    {
        $recovered = null;
        SyncPromise::rejected(new RuntimeException('boom'))
            ->then(null, static fn (\Throwable $e): string => 'recovered: '.$e->getMessage())
            ->then(function (mixed $value) use (&$recovered): void {
                $recovered = $value;
            });
        SyncPromise::runQueue();

        $this->assertSame('recovered: boom', $recovered);

        // Without a handler, the rejection stays rejected down the chain.
        $chain = SyncPromise::rejected(new RuntimeException('x'))->then(static fn (mixed $v): mixed => $v);
        SyncPromise::runQueue();
        $this->assertSame(SyncPromise::REJECTED, $chain->state);
    }

    public function test_dataloader_coalesces_at_scale_across_a_large_list(): void
    {
        $batchCalls = [];
        $loader = new DataLoader(function (array $keys) use (&$batchCalls): array {
            $batchCalls[] = $keys;

            return array_map(static fn (int|string $k): string => 'v'.$k, $keys);
        });

        $item = new ObjectType('Item', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('tag', Type::string(), resolve: static fn (array $row) => $loader->load($row['key'])),
        ]);
        $query = new ObjectType('Query', [
            FieldDefinition::make('items', Type::nonNull(Type::listOf(Type::nonNull($item))), resolve: static function (): array {
                $rows = [];
                for ($i = 0; $i < 1000; $i++) {
                    $rows[] = ['id' => (string) $i, 'key' => $i % 50]; // 50 distinct keys
                }

                return $rows;
            }),
        ]);
        $schema = new Schema(new SchemaConfig(query: $query));

        $result = Executor::execute($schema, Parser::parse('{ items { id tag } }'))->toArray();

        $this->assertCount(1000, $result['data']['items']);
        $this->assertSame('v0', $result['data']['items'][0]['tag']);
        $this->assertSame('v49', $result['data']['items'][49]['tag']);
        // Exactly one batch, with the 50 unique keys (deduplicated).
        $this->assertCount(1, $batchCalls);
        $this->assertCount(50, $batchCalls[0]);
    }
}
