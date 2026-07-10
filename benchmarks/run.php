<?php

declare(strict_types=1);

/**
 * Dependency-free micro-benchmark for the GraphQL engine (no Laravel needed).
 *
 * Runs each phase in isolation — parse, build, validate, execute — plus list
 * throughput and DataLoader batching. Reports the median over many iterations
 * (median is more stable than the mean under GC/JIT jitter).
 *
 * Usage:  php benchmarks/run.php
 */

use Hmennen90\GraphQL\Engine\Executor\DataLoader;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;

require __DIR__.'/../vendor/autoload.php';

/**
 * @param  callable():void  $fn
 * @return array{name: string, median_ns: float, ops: float}
 */
function bench(string $name, callable $fn, int $iterations = 400, int $warmup = 50): array
{
    for ($i = 0; $i < $warmup; $i++) {
        $fn();
    }

    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $fn();
        $times[] = hrtime(true) - $start;
    }
    sort($times);
    $median = (float) $times[intdiv(count($times), 2)];

    return ['name' => $name, 'median_ns' => $median, 'ops' => $median > 0 ? 1_000_000_000 / $median : 0.0];
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

$sdl = <<<'GRAPHQL'
type Query {
  hello: String
  users(count: Int): [User!]!
  orders(count: Int): [Order!]!
}
type User { id: ID! name: String email: String active: Boolean }
type Order { id: ID! total: Int customer: User }
GRAPHQL;

/** @return list<array{id: string, name: string, email: string, active: bool}> */
$makeUsers = static function (int $n): array {
    $users = [];
    for ($i = 1; $i <= $n; $i++) {
        $users[] = ['id' => (string) $i, 'name' => 'User '.$i, 'email' => 'u'.$i.'@example.test', 'active' => $i % 2 === 0];
    }

    return $users;
};

$buildSchema = static function () use ($sdl, $makeUsers): Schema {
    return SchemaBuilder::fromSdl($sdl, resolvers: [
        'Query' => [
            'hello' => static fn (): string => 'world',
            'users' => static fn ($root, array $args) => $makeUsers(is_int($args['count'] ?? null) ? $args['count'] : 100),
            'orders' => static function ($root, array $args): array {
                $count = is_int($args['count'] ?? null) ? $args['count'] : 100;
                $orders = [];
                for ($i = 1; $i <= $count; $i++) {
                    $orders[] = ['id' => (string) $i, 'total' => $i * 10, 'customerId' => (string) (($i % 20) + 1)];
                }

                return $orders;
            },
        ],
        'Order' => [
            // Resolve the customer through a per-request DataLoader passed as context.
            'customer' => static fn (array $order, array $args, mixed $ctx) => $ctx instanceof DataLoader
                ? $ctx->load($order['customerId'])
                : null,
        ],
    ]);
};

$schema = $buildSchema();

$flatDoc = Parser::parse('{ hello }');
$listQuery = '{ users(count: 100) { id name email active } }';
$listDoc = Parser::parse($listQuery);
$bigListDoc = Parser::parse('{ users(count: 1000) { id name email active } }');
$nestedQuery = '{ orders(count: 500) { id total customer { name email } } }';
$nestedDoc = Parser::parse($nestedQuery);

/** DataLoader batch: unique customers among 500 orders (ids 1..20). */
$customers = $makeUsers(20);
$makeLoader = static function () use ($customers): DataLoader {
    return new DataLoader(static function (array $keys) use ($customers): array {
        $byId = [];
        foreach ($customers as $c) {
            $byId[$c['id']] = $c;
        }

        return array_map(static fn ($k): mixed => $byId[$k] ?? null, $keys);
    });
};

// ---------------------------------------------------------------------------
// Correctness sanity — DataLoader must coalesce to a single batch.
// ---------------------------------------------------------------------------

$batchCalls = 0;
$countingLoader = new DataLoader(static function (array $keys) use ($customers, &$batchCalls): array {
    $batchCalls++;
    $byId = [];
    foreach ($customers as $c) {
        $byId[$c['id']] = $c;
    }

    return array_map(static fn ($k): mixed => $byId[$k] ?? null, $keys);
});
Executor::execute($schema, $nestedDoc, null, $countingLoader);

// ---------------------------------------------------------------------------
// Benchmarks
// ---------------------------------------------------------------------------

$results = [
    bench('parse: small query', static fn () => Parser::parse('{ hello }')),
    bench('parse: nested query', static fn () => Parser::parse($nestedQuery)),
    bench('build: schema from SDL', static fn () => $buildSchema(), 200),
    bench('validate: nested query', static fn () => DocumentValidator::validate($schema, $nestedDoc)),
    bench('execute: flat field', static fn () => Executor::execute($schema, $flatDoc)),
    bench('execute: list of 100', static fn () => Executor::execute($schema, $listDoc)),
    bench('execute: list of 1000', static fn () => Executor::execute($schema, $bigListDoc), 200),
    bench('execute: 500 nested + DataLoader', static fn () => Executor::execute($schema, $nestedDoc, null, $makeLoader()), 200),
    bench('full: parse+validate+execute (100)', static function () use ($schema, $listQuery): void {
        $doc = Parser::parse($listQuery);
        DocumentValidator::validate($schema, $doc);
        Executor::execute($schema, $doc);
    }),
];

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

printf("\nPHP %s  |  %s\n", PHP_VERSION, php_uname('s').' '.php_uname('m'));
printf("DataLoader batches for 500 orders / 20 customers: %d (expected 1)\n\n", $batchCalls);

printf("%-38s %14s %14s\n", 'scenario', 'ops/sec', 'median');
printf("%s\n", str_repeat('-', 68));
foreach ($results as $r) {
    $us = $r['median_ns'] / 1000;
    $time = $us >= 1000 ? sprintf('%.2f ms', $us / 1000) : sprintf('%.1f µs', $us);
    printf("%-38s %14s %14s\n", $r['name'], number_format($r['ops'], 0), $time);
}
printf("\npeak memory: %.1f MB\n\n", memory_get_peak_usage(true) / 1_048_576);
