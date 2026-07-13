<?php

declare(strict_types=1);

/**
 * Engine-to-engine benchmark: this package vs webonyx/graphql-php (the engine that
 * powers Lighthouse and rebing/graphql-laravel). Same schema, same queries, same
 * in-memory data — so it isolates raw engine throughput, not the Laravel/Eloquent
 * or directive layers a full Lighthouse request would add.
 *
 *   composer require --dev webonyx/graphql-php
 *   php benchmarks/vs-webonyx.php                      human-readable table (default)
 *   php benchmarks/vs-webonyx.php --json               comparison block as JSON to stdout
 *   php benchmarks/vs-webonyx.php --json=benchmarks.json   merge real numbers into that file
 *
 * With --json=path the `comparison` block of an existing benchmarks.json is replaced
 * in place with *measured* numbers (both engines on this runner, identical SDL + data),
 * leaving phases/scaling/history untouched. This is what turns the dashboard's
 * comparison chart from an illustrative placeholder into a real, same-hardware result.
 */

use GraphQL\Deferred as WebonyxDeferred;
use GraphQL\GraphQL as Webonyx;
use GraphQL\Language\Parser as WebonyxParser;
use GraphQL\Type\Definition\ObjectType as WebonyxObjectType;
use GraphQL\Type\Definition\Type as WebonyxType;
use GraphQL\Type\Schema as WebonyxSchema;
use GraphQL\Type\SchemaConfig as WebonyxSchemaConfig;
use GraphQL\Utils\BuildSchema as WebonyxBuildSchema;
use GraphQL\Validator\DocumentValidator as WebonyxValidator;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;

require __DIR__.'/../vendor/autoload.php';

if (! class_exists(Webonyx::class)) {
    fwrite(STDERR, "webonyx/graphql-php is not installed. Run: composer require --dev webonyx/graphql-php\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// CLI flags — mirror benchmarks/run.php so the two harnesses feel identical.
// ---------------------------------------------------------------------------

$emitJson = false;
$jsonPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
        $emitJson = true;
    } elseif (str_starts_with($arg, '--json=')) {
        $emitJson = true;
        $jsonPath = substr($arg, 7);
    }
}

// JSON-to-stdout must not be polluted by the human table.
$printTable = ! ($emitJson && $jsonPath === null);

/**
 * @param  callable():void  $fn
 * @return float median nanoseconds per op
 */
function median_ns(callable $fn, int $iterations = 300, int $warmup = 40): float
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

    return (float) $times[intdiv(count($times), 2)];
}

/** @return list<array{id: string, name: string, email: string, active: bool}> */
function make_users(int $n): array
{
    $users = [];
    for ($i = 1; $i <= $n; $i++) {
        $users[] = ['id' => (string) $i, 'name' => 'User '.$i, 'email' => 'u'.$i.'@example.test', 'active' => $i % 2 === 0];
    }

    return $users;
}

/**
 * Same shape run.php uses: 500 orders reference 20 distinct customers, so a batching
 * loader coalesces them into a single load.
 *
 * @return list<array{id: string, total: int, customerId: string}>
 */
function make_orders(int $n): array
{
    $orders = [];
    for ($i = 1; $i <= $n; $i++) {
        $orders[] = ['id' => (string) $i, 'total' => $i * 10, 'customerId' => (string) (($i % 20) + 1)];
    }

    return $orders;
}

/**
 * webonyx equivalent of our DataLoader: each load() queues a key and returns a
 * Deferred; the first Deferred to resolve performs a single batched lookup for every
 * key queued so far. One instance per request, exactly like run.php's loader.
 */
final class WebonyxUserLoader
{
    /** @var array<string, true> */
    private array $queue = [];

    /** @var array<string, mixed>|null */
    private ?array $result = null;

    /** @param  array<string, array<string, mixed>>  $byId */
    public function __construct(private readonly array $byId) {}

    public function load(string $id): WebonyxDeferred
    {
        $this->queue[$id] = true;

        return new WebonyxDeferred(function () use ($id): mixed {
            if ($this->result === null) {
                $this->result = [];
                foreach (array_keys($this->queue) as $key) {
                    $this->result[$key] = $this->byId[$key] ?? null;
                }
            }

            return $this->result[$id] ?? null;
        });
    }
}

// Identical SDL to benchmarks/run.php so every per-phase overlay is apples-to-apples.
$sdl = <<<'GRAPHQL'
type Query { hello: String users(count: Int): [User!]! orders(count: Int): [Order!]! }
type User { id: ID! name: String email: String active: Boolean }
type Order { id: ID! total: Int customer: User }
GRAPHQL;

// --- our engine ---
$ours = SchemaBuilder::fromSdl($sdl, resolvers: [
    'Query' => [
        'hello' => static fn (): string => 'world',
        'users' => static fn ($r, array $a) => make_users(is_int($a['count'] ?? null) ? $a['count'] : 100),
        'orders' => static fn ($r, array $a) => [],
    ],
]);

// --- webonyx (equivalent code-first schema) ---
$wUser = new WebonyxObjectType([
    'name' => 'User',
    'fields' => [
        'id' => WebonyxType::nonNull(WebonyxType::id()),
        'name' => WebonyxType::string(),
        'email' => WebonyxType::string(),
        'active' => WebonyxType::boolean(),
    ],
]);
$wOrder = new WebonyxObjectType([
    'name' => 'Order',
    'fields' => [
        'id' => WebonyxType::nonNull(WebonyxType::id()),
        'total' => WebonyxType::int(),
        // webonyx's Deferred is its DataLoader equivalent: returning one defers the
        // field so the executor drains all of them together (one batched load).
        'customer' => [
            'type' => $wUser,
            'resolve' => static fn (array $order, array $args, mixed $ctx) => is_object($ctx) && method_exists($ctx, 'load')
                ? $ctx->load($order['customerId'])
                : null,
        ],
    ],
]);
$wQuery = new WebonyxObjectType([
    'name' => 'Query',
    'fields' => [
        'hello' => ['type' => WebonyxType::string(), 'resolve' => static fn (): string => 'world'],
        'users' => [
            'type' => WebonyxType::nonNull(WebonyxType::listOf(WebonyxType::nonNull($wUser))),
            'args' => ['count' => WebonyxType::int()],
            'resolve' => static fn ($r, array $a) => make_users(is_int($a['count'] ?? null) ? $a['count'] : 100),
        ],
        'orders' => [
            'type' => WebonyxType::nonNull(WebonyxType::listOf(WebonyxType::nonNull($wOrder))),
            'args' => ['count' => WebonyxType::int()],
            'resolve' => static fn ($r, array $a) => make_orders(is_int($a['count'] ?? null) ? $a['count'] : 100),
        ],
    ],
]);
$webonyx = new WebonyxSchema(new WebonyxSchemaConfig()->setQuery($wQuery));

$flat = '{ hello }';
$nested = '{ orders(count: 500) { id total customer { name email } } }';
$list100 = '{ users(count: 100) { id name email active } }';
$list1000 = '{ users(count: 1000) { id name email active } }';

$oursFlat = Parser::parse($flat);
$oursList100 = Parser::parse($list100);
$oursList1000 = Parser::parse($list1000);
$wFlat = WebonyxParser::parse($flat);
$wNested = WebonyxParser::parse($nested);
$wList100 = WebonyxParser::parse($list100);
$wList1000 = WebonyxParser::parse($list1000);

$rows = [
    ['parse: list query', static fn () => Parser::parse($list100), static fn () => WebonyxParser::parse($list100)],
    ['validate: list query', static fn () => DocumentValidator::validate($ours, $oursList100), static fn () => WebonyxValidator::validate($webonyx, $wList100)],
    ['execute: flat field', static fn () => Executor::execute($ours, $oursFlat), static fn () => Webonyx::executeQuery($webonyx, $wFlat)->toArray()],
    ['execute: list of 100', static fn () => Executor::execute($ours, $oursList100), static fn () => Webonyx::executeQuery($webonyx, $wList100)->toArray()],
    ['execute: list of 1000', static fn () => Executor::execute($ours, $oursList1000), static fn () => Webonyx::executeQuery($webonyx, $wList1000)->toArray()],
];

$webonyxVersion = \Composer\InstalledVersions::getPrettyVersion('webonyx/graphql-php') ?? 'unknown';

// Measure both engines for every scenario up front so the table and the JSON
// are two views of one measurement set (same guarantee run.php makes).
$measured = [];
foreach ($rows as [$name, $oursFn, $webFn]) {
    $measured[] = ['label' => $name, 'oursNs' => median_ns($oursFn), 'webonyxNs' => median_ns($webFn)];
}

if ($printTable) {
    $fmt = static function (float $ns): string {
        $us = $ns / 1000;

        return $us >= 1000 ? sprintf('%.2f ms', $us / 1000) : sprintf('%.1f µs', $us);
    };

    printf("\nPHP %s  |  %s\n", PHP_VERSION, php_uname('s').' '.php_uname('m'));
    printf("laravel-graphql vs webonyx/graphql-php %s  (median per op)\n\n", $webonyxVersion);
    printf("%-24s %14s %14s %10s\n", 'scenario', 'ours', 'webonyx', 'ratio');
    printf("%s\n", str_repeat('-', 66));

    foreach ($measured as $m) {
        $ratio = $m['webonyxNs'] > 0 ? $m['oursNs'] / $m['webonyxNs'] : 0.0;
        $verdict = $ratio <= 1 ? sprintf('%.2fx faster', 1 / $ratio) : sprintf('%.2fx slower', $ratio);
        printf("%-24s %14s %14s %10s\n", $m['label'], $fmt($m['oursNs']), $fmt($m['webonyxNs']), $verdict);
    }
    echo "\n(ratio = ours / webonyx; <1x means this package is faster)\n\n";
}

// ---------------------------------------------------------------------------
// JSON emission — the `comparison` block the dashboard consumes.
// ---------------------------------------------------------------------------

if ($emitJson) {
    $comparison = [
        'note' => sprintf(
            'Measured engine-to-engine on one machine (webonyx/graphql-php %s): identical SDL and '
            .'in-memory data, isolating raw execution — no Laravel/Eloquent/HTTP layer. See '
            .'docs/benchmarks.md "Versus webonyx/graphql-php" for the method and caveats.',
            $webonyxVersion
        ),
        'unit' => 'ms',
        'measured' => true,
        'comparedVersion' => $webonyxVersion,
        'scenarios' => array_map(static fn (array $m): array => [
            'label' => $m['label'],
            'thisEngineMs' => round($m['oursNs'] / 1_000_000, 4),
            'webonyxMs' => round($m['webonyxNs'] / 1_000_000, 4),
        ], $measured),
    ];

    // Per-phase / per-scenario webonyx overlays, keyed by the same ids run.php emits,
    // so the dashboard draws a webonyx series next to ours in every chart. Every
    // run.php scenario has a fair webonyx equivalent — including the DataLoader batch
    // (webonyx Deferred) and the full parse+validate+execute pipeline.
    $phaseWebonyxUs = [
        'parse_small' => median_ns(static fn () => WebonyxParser::parse($flat)) / 1000,
        'parse_nested' => median_ns(static fn () => WebonyxParser::parse($nested)) / 1000,
        'build_schema' => median_ns(static fn () => WebonyxBuildSchema::build($sdl)) / 1000,
        'validate_nested' => median_ns(static fn () => WebonyxValidator::validate($webonyx, $wNested)) / 1000,
        'execute_flat' => median_ns(static fn () => Webonyx::executeQuery($webonyx, $wFlat)->toArray()) / 1000,
    ];

    // Fresh loader per iteration, exactly like run.php's per-request DataLoader.
    $customersById = [];
    foreach (make_users(20) as $customer) {
        $customersById[$customer['id']] = $customer;
    }

    // Heavier executions use fewer iterations (median stays stable) so the whole
    // sweep — CI included — finishes quickly.
    $scalingWebonyxMs = [
        'execute_list_100' => median_ns(static fn () => Webonyx::executeQuery($webonyx, $wList100)->toArray(), 200) / 1_000_000,
        'execute_list_1000' => median_ns(static fn () => Webonyx::executeQuery($webonyx, $wList1000)->toArray(), 120) / 1_000_000,
        'execute_nested_500' => median_ns(static fn () => Webonyx::executeQuery(
            $webonyx,
            $wNested,
            null,
            new WebonyxUserLoader($customersById),
        )->toArray(), 120) / 1_000_000,
        'full_100' => median_ns(static function () use ($webonyx, $list100): void {
            $doc = WebonyxParser::parse($list100);
            WebonyxValidator::validate($webonyx, $doc);
            Webonyx::executeQuery($webonyx, $doc)->toArray();
        }, 200) / 1_000_000,
    ];

    if ($jsonPath !== null) {
        // Merge into an existing benchmarks.json: replace `comparison` and add a
        // webonyx value onto every phase/scaling entry that has a measured counterpart.
        $doc = [];
        if (is_file($jsonPath)) {
            /** @var array<string, mixed> $doc */
            $doc = json_decode((string) file_get_contents($jsonPath), true) ?: [];
        }
        if (isset($doc['phases']) && is_array($doc['phases'])) {
            foreach ($doc['phases'] as &$phase) {
                $id = $phase['id'] ?? null;
                if (is_string($id) && isset($phaseWebonyxUs[$id])) {
                    $phase['webonyxUs'] = round($phaseWebonyxUs[$id], 2);
                }
            }
            unset($phase);
        }
        if (isset($doc['scaling']) && is_array($doc['scaling'])) {
            foreach ($doc['scaling'] as &$scale) {
                $id = $scale['id'] ?? null;
                if (is_string($id) && isset($scalingWebonyxMs[$id])) {
                    $scale['webonyxMs'] = round($scalingWebonyxMs[$id], 4);
                }
            }
            unset($scale);
        }
        $doc['comparison'] = $comparison;
        file_put_contents(
            $jsonPath,
            json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );
        if ($printTable) {
            printf("merged measured comparison into %s\n", $jsonPath);
        }
    } else {
        echo json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
