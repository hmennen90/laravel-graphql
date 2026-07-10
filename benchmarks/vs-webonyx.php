<?php

declare(strict_types=1);

/**
 * Engine-to-engine benchmark: this package vs webonyx/graphql-php (the engine that
 * powers Lighthouse and rebing/graphql-laravel). Same schema, same queries, same
 * in-memory data — so it isolates raw engine throughput, not the Laravel/Eloquent
 * or directive layers a full Lighthouse request would add.
 *
 *   composer require --dev webonyx/graphql-php
 *   php benchmarks/vs-webonyx.php
 */

use GraphQL\GraphQL as Webonyx;
use GraphQL\Language\Parser as WebonyxParser;
use GraphQL\Type\Definition\ObjectType as WebonyxObjectType;
use GraphQL\Type\Definition\Type as WebonyxType;
use GraphQL\Type\Schema as WebonyxSchema;
use GraphQL\Type\SchemaConfig as WebonyxSchemaConfig;
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

$sdl = <<<'GRAPHQL'
type Query { hello: String users(count: Int): [User!]! }
type User { id: ID! name: String email: String active: Boolean }
GRAPHQL;

// --- our engine ---
$ours = SchemaBuilder::fromSdl($sdl, resolvers: [
    'Query' => [
        'hello' => static fn (): string => 'world',
        'users' => static fn ($r, array $a) => make_users(is_int($a['count'] ?? null) ? $a['count'] : 100),
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
$wQuery = new WebonyxObjectType([
    'name' => 'Query',
    'fields' => [
        'hello' => ['type' => WebonyxType::string(), 'resolve' => static fn (): string => 'world'],
        'users' => [
            'type' => WebonyxType::nonNull(WebonyxType::listOf(WebonyxType::nonNull($wUser))),
            'args' => ['count' => WebonyxType::int()],
            'resolve' => static fn ($r, array $a) => make_users(is_int($a['count'] ?? null) ? $a['count'] : 100),
        ],
    ],
]);
$webonyx = new WebonyxSchema(new WebonyxSchemaConfig()->setQuery($wQuery));

$flat = '{ hello }';
$list100 = '{ users(count: 100) { id name email active } }';
$list1000 = '{ users(count: 1000) { id name email active } }';

$oursFlat = Parser::parse($flat);
$oursList100 = Parser::parse($list100);
$oursList1000 = Parser::parse($list1000);
$wFlat = WebonyxParser::parse($flat);
$wList100 = WebonyxParser::parse($list100);
$wList1000 = WebonyxParser::parse($list1000);

$rows = [
    ['parse: list query', static fn () => Parser::parse($list100), static fn () => WebonyxParser::parse($list100)],
    ['validate: list query', static fn () => DocumentValidator::validate($ours, $oursList100), static fn () => WebonyxValidator::validate($webonyx, $wList100)],
    ['execute: flat field', static fn () => Executor::execute($ours, $oursFlat), static fn () => Webonyx::executeQuery($webonyx, $wFlat)->toArray()],
    ['execute: list of 100', static fn () => Executor::execute($ours, $oursList100), static fn () => Webonyx::executeQuery($webonyx, $wList100)->toArray()],
    ['execute: list of 1000', static fn () => Executor::execute($ours, $oursList1000), static fn () => Webonyx::executeQuery($webonyx, $wList1000)->toArray()],
];

printf("\nPHP %s  |  %s\n", PHP_VERSION, php_uname('s').' '.php_uname('m'));
printf("laravel-graphql vs webonyx/graphql-php %s  (median per op)\n\n", \Composer\InstalledVersions::getPrettyVersion('webonyx/graphql-php'));
printf("%-24s %14s %14s %10s\n", 'scenario', 'ours', 'webonyx', 'ratio');
printf("%s\n", str_repeat('-', 66));

$fmt = static function (float $ns): string {
    $us = $ns / 1000;

    return $us >= 1000 ? sprintf('%.2f ms', $us / 1000) : sprintf('%.1f µs', $us);
};

foreach ($rows as [$name, $oursFn, $webFn]) {
    $o = median_ns($oursFn);
    $w = median_ns($webFn);
    $ratio = $w > 0 ? $o / $w : 0.0;
    $verdict = $ratio <= 1 ? sprintf('%.2fx faster', 1 / $ratio) : sprintf('%.2fx slower', $ratio);
    printf("%-24s %14s %14s %10s\n", $name, $fmt($o), $fmt($w), $verdict);
}
echo "\n(ratio = ours / webonyx; <1x means this package is faster)\n\n";
