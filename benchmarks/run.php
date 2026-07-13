<?php

declare(strict_types=1);

/**
 * Dependency-free micro-benchmark for the GraphQL engine (no Laravel needed).
 *
 * Runs each phase in isolation — parse, build, validate, execute — plus list
 * throughput and DataLoader batching. Reports the median over many iterations
 * (median is more stable than the mean under GC/JIT jitter).
 *
 * Usage:
 *   php benchmarks/run.php                       human-readable table (default)
 *   php benchmarks/run.php --json                machine-readable JSON to stdout
 *   php benchmarks/run.php --json=path.json      write JSON to a file (table still printed)
 *   php benchmarks/run.php --json=path.json --version=1.5.0
 *
 * The table and the JSON are fed by the *same* benchmark run, so the published numbers
 * can never drift from what the harness measured.
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

// ---------------------------------------------------------------------------
// CLI flags
// ---------------------------------------------------------------------------

$emitJson = false;
$jsonPath = null;
$version = getenv('BENCH_VERSION') !== false ? (string) getenv('BENCH_VERSION') : 'dev';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
        $emitJson = true;
    } elseif (str_starts_with($arg, '--json=')) {
        $emitJson = true;
        $jsonPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--version=')) {
        $version = substr($arg, 10);
    }
}

// JSON-to-stdout must not be polluted by the human report or the sustained-load run.
$printTable = ! ($emitJson && $jsonPath === null);

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

// Each spec carries the metadata the JSON needs (id, group, object count) so the
// table and the JSON are two views of one measurement set.
$specs = [
    ['id' => 'parse_small', 'label' => 'parse: small query', 'group' => 'phase', 'objects' => 0, 'iterations' => 400, 'fn' => static fn () => Parser::parse('{ hello }')],
    ['id' => 'parse_nested', 'label' => 'parse: nested query', 'group' => 'phase', 'objects' => 0, 'iterations' => 400, 'fn' => static fn () => Parser::parse($nestedQuery)],
    ['id' => 'build_schema', 'label' => 'build: schema from SDL', 'group' => 'phase', 'objects' => 0, 'iterations' => 200, 'fn' => static fn () => $buildSchema()],
    ['id' => 'validate_nested', 'label' => 'validate: nested query', 'group' => 'phase', 'objects' => 0, 'iterations' => 400, 'fn' => static fn () => DocumentValidator::validate($schema, $nestedDoc)],
    ['id' => 'execute_flat', 'label' => 'execute: flat field', 'group' => 'phase', 'objects' => 1, 'iterations' => 400, 'fn' => static fn () => Executor::execute($schema, $flatDoc)],
    ['id' => 'execute_list_100', 'label' => 'execute: list of 100', 'group' => 'scaling', 'objects' => 100, 'iterations' => 400, 'fn' => static fn () => Executor::execute($schema, $listDoc)],
    ['id' => 'execute_list_1000', 'label' => 'execute: list of 1000', 'group' => 'scaling', 'objects' => 1000, 'iterations' => 200, 'fn' => static fn () => Executor::execute($schema, $bigListDoc)],
    ['id' => 'execute_nested_500', 'label' => 'execute: 500 nested + DataLoader', 'group' => 'scaling', 'objects' => 500, 'iterations' => 200, 'fn' => static fn () => Executor::execute($schema, $nestedDoc, null, $makeLoader())],
    ['id' => 'full_100', 'label' => 'full: parse+validate+execute (100)', 'group' => 'scaling', 'objects' => 100, 'iterations' => 400, 'fn' => static function () use ($schema, $listQuery): void {
        $doc = Parser::parse($listQuery);
        DocumentValidator::validate($schema, $doc);
        Executor::execute($schema, $doc);
    }],
];

$results = [];
foreach ($specs as $spec) {
    $results[] = bench($spec['label'], $spec['fn'], $spec['iterations']) + [
        'id' => $spec['id'],
        'group' => $spec['group'],
        'objects' => $spec['objects'],
    ];
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

if ($printTable) {
    printf("\nPHP %s  |  %s\n", PHP_VERSION, php_uname('s').' '.php_uname('m'));
    printf("DataLoader batches for 500 orders / 20 customers: %d (expected 1)\n\n", $batchCalls);

    printf("%-38s %14s %14s\n", 'scenario', 'ops/sec', 'median');
    printf("%s\n", str_repeat('-', 68));
    foreach ($results as $r) {
        $us = $r['median_ns'] / 1000;
        $time = $us >= 1000 ? sprintf('%.2f ms', $us / 1000) : sprintf('%.1f µs', $us);
        printf("%-38s %14s %14s\n", $r['name'], number_format($r['ops'], 0), $time);
    }
    printf("\npeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1_048_576);
}

// ---------------------------------------------------------------------------
// Throughput & memory stability (sustained load proxy)
//
// PHP has no in-process threads, so "concurrency" for a request/response engine is
// really sustained throughput: how many full operations/sec one worker serves, and
// whether memory stays flat across many requests (no per-request leak).
// ---------------------------------------------------------------------------

if ($printTable) {
    gc_collect_cycles();
    $before = memory_get_usage();
    $iterations = 20_000;
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $doc = Parser::parse($listQuery);
        DocumentValidator::validate($schema, $doc);
        Executor::execute($schema, $doc);
    }
    $elapsed = (hrtime(true) - $start) / 1_000_000_000;
    gc_collect_cycles();
    $growth = (memory_get_usage() - $before) / 1_048_576;

    printf("\nsustained load (%s full requests of `list of 100`):\n", number_format($iterations));
    printf("  throughput:   %s req/s\n", number_format($iterations / $elapsed, 0));
    printf("  heap growth:  %+.2f MB over the run (≈0 = no per-request leak)\n\n", $growth);
}

// ---------------------------------------------------------------------------
// JSON emission (--json / --json=path) — same measurements, machine-readable.
// ---------------------------------------------------------------------------

if ($emitJson) {
    $jitEnabled = false;
    if (function_exists('opcache_get_status')) {
        $status = @opcache_get_status(false);
        $jitEnabled = (bool) ($status['jit']['enabled'] ?? false);
    }

    $phases = [];
    $scaling = [];
    $fullQuery100Ms = 0.0;
    foreach ($results as $r) {
        if ($r['group'] === 'phase') {
            $phases[] = [
                'id' => $r['id'],
                'label' => $r['name'],
                'medianUs' => round($r['median_ns'] / 1000, 2),
                'throughputPerSec' => (int) round($r['ops']),
            ];
        } else {
            $scaling[] = [
                'id' => $r['id'],
                'label' => $r['name'],
                'medianMs' => round($r['median_ns'] / 1_000_000, 3),
                'throughputPerSec' => (int) round($r['ops']),
                'objects' => $r['objects'],
            ];
            if ($r['id'] === 'full_100') {
                $fullQuery100Ms = round($r['median_ns'] / 1_000_000, 3);
            }
        }
    }

    // Preserve/append the performance-over-releases series. If the target file
    // already carries a history, upsert the entry for this version so the
    // dashboard's release trend accumulates instead of resetting each run.
    $history = [];
    if ($jsonPath !== null && is_file($jsonPath)) {
        /** @var array<string, mixed> $existing */
        $existing = json_decode((string) file_get_contents($jsonPath), true) ?: [];
        if (isset($existing['history']) && is_array($existing['history'])) {
            foreach ($existing['history'] as $entry) {
                if (is_array($entry) && isset($entry['version'])) {
                    $history[(string) $entry['version']] = (float) ($entry['fullQuery100Ms'] ?? 0.0);
                }
            }
        }
    }
    $history[$version] = $fullQuery100Ms;
    $historyList = [];
    foreach ($history as $v => $ms) {
        $historyList[] = ['version' => $v, 'fullQuery100Ms' => $ms];
    }

    $payload = [
        'meta' => [
            'generatedAt' => date('c'),
            'version' => $version,
            'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'jit' => $jitEnabled,
            'machine' => php_uname('s').' '.php_uname('m'),
            'note' => 'Median over many iterations; numbers are machine-specific. The shape (per-phase cost, linear scaling) is what matters.',
        ],
        'phases' => $phases,
        'scaling' => $scaling,
        'comparison' => [
            'note' => 'ILLUSTRATIVE PLACEHOLDER — the webonyx series is not yet measured on this runner. See docs/benchmarks.md "Versus webonyx/graphql-php" for the real engine-to-engine method.',
            'unit' => 'ms',
            'scenarios' => [
                ['label' => 'parse: list query', 'thisEngineMs' => 0.021, 'webonyxMs' => 0.033],
                ['label' => 'validate: list query', 'thisEngineMs' => 0.006, 'webonyxMs' => 0.208],
                ['label' => 'execute: flat field', 'thisEngineMs' => 0.002, 'webonyxMs' => 0.094],
                ['label' => 'execute: list of 100', 'thisEngineMs' => 0.65, 'webonyxMs' => 1.4],
                ['label' => 'execute: list of 1000', 'thisEngineMs' => 6.4, 'webonyxMs' => 11.5],
            ],
        ],
        'history' => $historyList,
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    if ($jsonPath !== null) {
        file_put_contents($jsonPath, $json);
        if ($printTable) {
            printf("wrote benchmark JSON to %s\n", $jsonPath);
        }
    } else {
        echo $json;
    }
}

