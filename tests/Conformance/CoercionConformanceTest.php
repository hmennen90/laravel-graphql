<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Spec conformance for input/scalar/variable coercion ("Coercing Field Arguments"
 * and "Coercing Variable Values"). These are the long-tail edge cases where a
 * hand-written engine most often diverges from graphql-js.
 */
final class CoercionConformanceTest extends TestCase
{
    private function schema(): Schema
    {
        $sdl = <<<'GRAPHQL'
        type Query {
          int(x: Int): Int
          float(x: Float): Float
          id(x: ID): ID
          list(xs: [Int!]): [Int!]
          reqList(xs: [Int!]!): Int
          color(c: Color!): String
          need(x: Int!): Int
        }
        enum Color { RED GREEN }
        GRAPHQL;

        return SchemaBuilder::fromSdl($sdl, ['Query' => [
            'int' => static fn ($r, array $a) => $a['x'] ?? null,
            'float' => static fn ($r, array $a) => $a['x'] ?? null,
            'id' => static fn ($r, array $a) => $a['x'] ?? null,
            'list' => static fn ($r, array $a) => $a['xs'] ?? null,
            'reqList' => static fn ($r, array $a) => is_array($a['xs']) ? count($a['xs']) : 0,
            'color' => static fn ($r, array $a) => $a['c'] ?? null,
            'need' => static fn ($r, array $a) => $a['x'] ?? null,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function exec(string $query, array $variables = []): array
    {
        $schema = $this->schema();
        $document = Parser::parse($query);

        // Mirror the request pipeline: validate first, then execute.
        $errors = \Hmennen90\GraphQL\Engine\Validation\DocumentValidator::validate($schema, $document);
        if ($errors !== []) {
            return \Hmennen90\GraphQL\Engine\Executor\ExecutionResult::withErrors($errors)->toArray();
        }

        return Executor::execute($schema, $document, variableValues: $variables)->toArray();
    }

    public function test_float_accepts_integer_input(): void
    {
        $this->assertSame(3.0, $this->exec('{ float(x: 3) }')['data']['float']);
    }

    public function test_id_accepts_int_and_string(): void
    {
        $this->assertSame('5', $this->exec('{ id(x: 5) }')['data']['id']);
        $this->assertSame('abc', $this->exec('{ id(x: "abc") }')['data']['id']);
    }

    public function test_int_rejects_non_integer_variable(): void
    {
        $result = $this->exec('query ($x: Int) { int(x: $x) }', ['x' => 1.5]);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_int_rejects_out_of_range_variable(): void
    {
        $result = $this->exec('query ($x: Int) { int(x: $x) }', ['x' => 2_147_483_648]);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_non_null_argument_rejects_null_literal(): void
    {
        $result = $this->exec('{ need(x: null) }');
        $this->assertArrayHasKey('errors', $result);
        $this->assertNull($result['data'] ?? null);
    }

    public function test_required_list_rejects_null_variable(): void
    {
        $result = $this->exec('query ($xs: [Int!]!) { reqList(xs: $xs) }', ['xs' => null]);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_invalid_enum_variable_is_rejected(): void
    {
        $result = $this->exec('query ($c: Color!) { color(c: $c) }', ['c' => 'PURPLE']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_single_value_coerces_to_single_item_list(): void
    {
        // Spec: a non-list value where a list is expected becomes a one-element list.
        $result = $this->exec('query ($xs: [Int!]) { list(xs: $xs) }', ['xs' => 5]);
        $this->assertSame([5], $result['data']['list']);
    }
}
