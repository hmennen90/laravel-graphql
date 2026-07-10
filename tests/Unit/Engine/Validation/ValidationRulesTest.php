<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Validation;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class ValidationRulesTest extends TestCase
{
    private function schema(): Schema
    {
        return SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query {
              hello: String!
              echo(msg: String!): String
              node(id: ID!): Node
              pet: CatOrDog
            }

            interface Node { id: ID! }
            type Cat implements Node { id: ID! meow: String }
            type Dog implements Node { id: ID! bark: String }
            union CatOrDog = Cat | Dog
            GRAPHQL);
    }

    private function validate(string $query): string
    {
        $errors = DocumentValidator::validate($this->schema(), Parser::parse($query));

        return implode("\n", array_map(static fn ($e): string => $e->getMessage(), $errors));
    }

    public function test_known_type_names_in_fragments(): void
    {
        $this->assertStringContainsString('Unknown type "Missing"', $this->validate(
            '{ node(id: "1") { ... on Missing { id } } }',
        ));
    }

    public function test_fragments_on_composite_types(): void
    {
        $this->assertStringContainsString('can only be on', $this->validate(
            '{ hello ...F } fragment F on String { length }',
        ));
    }

    public function test_variables_are_input_types(): void
    {
        $this->assertStringContainsString('is not an input type', $this->validate(
            'query ($n: Node) { hello }',
        ));
    }

    public function test_variables_in_allowed_position(): void
    {
        $this->assertStringContainsString('cannot be used', $this->validate(
            'query ($m: String) { echo(msg: $m) }',
        ));
    }

    public function test_possible_fragment_spreads(): void
    {
        $this->assertStringContainsString('cannot be spread', $this->validate(
            '{ node(id: "1") { ... on Query { hello } } }',
        ));
    }

    public function test_unique_operation_names(): void
    {
        $this->assertStringContainsString('one operation named "Foo"', $this->validate(
            'query Foo { hello } query Foo { hello }',
        ));
    }

    public function test_unique_variable_names(): void
    {
        $this->assertStringContainsString('one variable named "$x"', $this->validate(
            'query ($x: ID!, $x: ID!) { hello }',
        ));
    }

    public function test_unique_argument_names(): void
    {
        $this->assertStringContainsString('one argument named "msg"', $this->validate(
            '{ echo(msg: "a", msg: "b") }',
        ));
    }

    public function test_unique_fragment_names(): void
    {
        $this->assertStringContainsString('one fragment named "F"', $this->validate(
            '{ hello ...F } fragment F on Query { hello } fragment F on Query { hello }',
        ));
    }

    public function test_lone_anonymous_operation(): void
    {
        $this->assertStringContainsString('anonymous operation', $this->validate(
            '{ hello } query Named { hello }',
        ));
    }

    public function test_known_directives(): void
    {
        $this->assertStringContainsString('Unknown directive "@bogus"', $this->validate(
            '{ hello @bogus }',
        ));
    }

    public function test_directive_in_valid_location(): void
    {
        $this->assertStringContainsString('cannot be used', $this->validate(
            'query @skip(if: true) { hello }',
        ));
    }

    public function test_unique_directives_per_location(): void
    {
        $this->assertStringContainsString('once per location', $this->validate(
            '{ hello @skip(if: true) @skip(if: false) }',
        ));
    }

    public function test_directive_required_arguments(): void
    {
        $this->assertStringContainsString('argument "if"', $this->validate(
            '{ hello @skip }',
        ));
    }

    public function test_valid_document_passes(): void
    {
        $this->assertSame('', $this->validate(
            'query ($id: ID!) { node(id: $id) { id ... on Cat { meow } } pet { ... on Dog { bark } } }',
        ));
    }
}
