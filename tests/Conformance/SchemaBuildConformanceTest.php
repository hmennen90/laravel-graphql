<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Asserts observable outcomes of SDL schema building (default values, type
 * extensions, descriptions) — behaviours the executor/introspection tests exercise
 * structurally but did not previously assert, so build-time mutants survived.
 */
final class SchemaBuildConformanceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function introspect(Schema $schema, string $query): array
    {
        return Executor::execute($schema, Parser::parse($query))->toArray()['data'];
    }

    public function test_argument_default_value_is_applied_and_introspectable(): void
    {
        $schema = SchemaBuilder::fromSdl(
            'type Query { withDefault(n: Int = 41): Int }',
            ['Query' => ['withDefault' => static fn ($r, array $a): int => (int) $a['n'] + 1]],
        );

        // Applied at execution when the argument is omitted.
        $this->assertSame(42, Executor::execute($schema, Parser::parse('{ withDefault }'))->toArray()['data']['withDefault']);

        // And exposed via introspection as the default value literal.
        $data = $this->introspect($schema, '{ __type(name: "Query") { fields { args { name defaultValue } } } }');
        $arg = $data['__type']['fields'][0]['args'][0];
        $this->assertSame('n', $arg['name']);
        $this->assertSame('41', $arg['defaultValue']);
    }

    public function test_input_object_field_default_value(): void
    {
        $schema = SchemaBuilder::fromSdl(
            'type Query { ping(in: In): String } input In { mode: String = "fast" }',
            ['Query' => ['ping' => static fn (): string => 'ok']],
        );

        $data = $this->introspect($schema, '{ __type(name: "In") { inputFields { name defaultValue } } }');
        $field = $data['__type']['inputFields'][0];
        $this->assertSame('mode', $field['name']);
        $this->assertSame('"fast"', $field['defaultValue']);
    }

    public function test_type_extension_merges_fields(): void
    {
        $schema = SchemaBuilder::fromSdl(
            'type Query { a: String } extend type Query { b: String }',
            ['Query' => ['a' => static fn (): string => 'A', 'b' => static fn (): string => 'B']],
        );

        $result = Executor::execute($schema, Parser::parse('{ a b }'))->toArray();
        $this->assertSame(['a' => 'A', 'b' => 'B'], $result['data']);
    }

    public function test_descriptions_are_carried_into_introspection(): void
    {
        $schema = SchemaBuilder::fromSdl(
            '"The root query." type Query { "greet the world" hello: String }',
            ['Query' => ['hello' => static fn (): string => 'hi']],
        );

        $data = $this->introspect($schema, '{ __type(name: "Query") { description fields { name description } } }');
        $this->assertSame('The root query.', $data['__type']['description']);
        $this->assertSame('greet the world', $data['__type']['fields'][0]['description']);
    }

    public function test_default_value_is_only_applied_when_argument_is_absent(): void
    {
        $schema = SchemaBuilder::fromSdl(
            'type Query { echo(n: Int = 41): Int }',
            ['Query' => ['echo' => static fn ($r, array $a): int => (int) $a['n']]],
        );

        // Explicit value wins over the default.
        $this->assertSame(7, Executor::execute($schema, Parser::parse('{ echo(n: 7) }'))->toArray()['data']['echo']);
        $this->assertSame(41, Executor::execute($schema, Parser::parse('{ echo }'))->toArray()['data']['echo']);
    }
}
