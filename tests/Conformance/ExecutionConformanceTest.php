<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL spec conformance — execution semantics (June 2018 spec, "Execution" and
 * "Value Completion" sections): coercion, null propagation, lists, abstract types,
 * aliases, fragments, variable coercion.
 */
final class ExecutionConformanceTest extends TestCase
{
    private function schema(): Schema
    {
        $sdl = <<<'GRAPHQL'
        type Query {
          int: Int
          intFromString: Int
          float: Float
          bool: Boolean
          id: ID
          enum: Color
          badEnum: Color
          list: [Int]
          listWithNull: [Int]
          nonNullItemList: [Int!]
          nonNull: Int!
          nonNullNull: Int!
          nested: Nested
          node: Node
          result: SearchResult
          withArgs(a: Int!, b: Int = 5): Int
          echoInput(in: MyInput!): String
        }
        type Nested { value: String deep: Nested }
        enum Color { RED GREEN BLUE }
        interface Node { id: ID! }
        type Thing implements Node { id: ID! name: String }
        type Nested2 implements Node { id: ID! }
        union SearchResult = Thing | Nested
        input MyInput { a: Int! b: String c: Color }
        GRAPHQL;

        $resolvers = [
            'Query' => [
                'int' => static fn (): int => 42,
                'intFromString' => static fn (): string => '17',
                'float' => static fn (): float => 3.5,
                'bool' => static fn (): bool => true,
                'id' => static fn (): int => 7,
                'enum' => static fn (): string => 'GREEN',
                'badEnum' => static fn (): string => 'PURPLE',
                'list' => static fn (): array => [1, 2, 3],
                'listWithNull' => static fn (): array => [1, null, 3],
                'nonNullItemList' => static fn (): array => [1, null, 3],
                'nonNull' => static fn (): int => 1,
                'nonNullNull' => static fn () => null,
                'nested' => static fn (): array => ['value' => 'hi'],
                'node' => static fn (): array => ['__t' => 'Thing', 'id' => '1', 'name' => 'x'],
                'result' => static fn (): array => ['__t' => 'Thing', 'id' => '1', 'name' => 'x'],
                'withArgs' => static fn ($r, array $a): int => (int) $a['a'] + (int) $a['b'],
                'echoInput' => static fn ($r, array $a): string => json_encode($a['in'], JSON_THROW_ON_ERROR),
            ],
            'Nested' => ['value' => static fn ($s) => $s['value'] ?? null],
        ];
        $typeResolvers = [
            'Node' => static fn ($v): string => is_array($v) && isset($v['__t']) ? $v['__t'] : 'Thing',
            'SearchResult' => static fn ($v): string => is_array($v) && isset($v['__t']) ? $v['__t'] : 'Thing',
        ];

        return SchemaBuilder::fromSdl($sdl, $resolvers, $typeResolvers);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function exec(string $query, array $variables = []): array
    {
        return Executor::execute($this->schema(), Parser::parse($query), variableValues: $variables)->toArray();
    }

    public function test_scalar_coercion(): void
    {
        $data = $this->exec('{ int intFromString float bool id enum }')['data'];
        $this->assertSame(42, $data['int']);
        $this->assertSame(17, $data['intFromString']); // String "17" coerced to Int
        $this->assertSame(3.5, $data['float']);
        $this->assertTrue($data['bool']);
        $this->assertSame('7', $data['id']); // ID always serialized as String
        $this->assertSame('GREEN', $data['enum']);
    }

    public function test_invalid_enum_value_is_a_field_error(): void
    {
        $result = $this->exec('{ badEnum }');
        $this->assertNull($result['data']['badEnum']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_null_propagates_to_nearest_nullable_ancestor(): void
    {
        // nonNullNull returns null for a non-null field → data.nonNullNull errors,
        // but since Query.nonNullNull is a root nullable position wrapper, data is null.
        $result = $this->exec('{ nonNullNull }');
        $this->assertArrayHasKey('errors', $result);
        $this->assertNull($result['data']);
    }

    public function test_list_with_null_items_is_allowed_when_item_is_nullable(): void
    {
        $this->assertSame([1, null, 3], $this->exec('{ listWithNull }')['data']['listWithNull']);
    }

    public function test_null_item_in_non_null_item_list_nulls_the_list(): void
    {
        $result = $this->exec('{ nonNullItemList }');
        $this->assertNull($result['data']['nonNullItemList']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_aliases(): void
    {
        $this->assertSame(['a' => 42, 'b' => 42], $this->exec('{ a: int b: int }')['data']);
    }

    public function test_typename_meta_field(): void
    {
        $this->assertSame('Query', $this->exec('{ __typename }')['data']['__typename']);
        $this->assertSame('Thing', $this->exec('{ node { __typename } }')['data']['node']['__typename']);
    }

    public function test_interface_and_union_resolution_with_inline_fragments(): void
    {
        $node = $this->exec('{ node { ... on Thing { name } } }')['data']['node'];
        $this->assertSame('x', $node['name']);

        $result = $this->exec('{ result { ... on Thing { id name } } }')['data']['result'];
        $this->assertSame(['id' => '1', 'name' => 'x'], $result);
    }

    public function test_fragment_spread(): void
    {
        $q = 'query { nested { ...F } } fragment F on Nested { value }';
        $this->assertSame('hi', $this->exec($q)['data']['nested']['value']);
    }

    public function test_argument_default_value(): void
    {
        $this->assertSame(15, $this->exec('{ withArgs(a: 10) }')['data']['withArgs']);
        $this->assertSame(12, $this->exec('{ withArgs(a: 10, b: 2) }')['data']['withArgs']);
    }

    public function test_variable_coercion_and_defaults(): void
    {
        $q = 'query ($a: Int!, $b: Int) { withArgs(a: $a, b: $b) }';
        $this->assertSame(8, $this->exec($q, ['a' => 3, 'b' => 5])['data']['withArgs']);
    }

    public function test_input_object_coercion(): void
    {
        $q = 'query ($in: MyInput!) { echoInput(in: $in) }';
        $out = $this->exec($q, ['in' => ['a' => 1, 'b' => 'x', 'c' => 'RED']])['data']['echoInput'];
        $this->assertStringContainsString('"a":1', (string) $out);
        $this->assertStringContainsString('"c":"RED"', (string) $out);
    }
}
