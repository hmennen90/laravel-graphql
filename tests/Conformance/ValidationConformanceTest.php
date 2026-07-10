<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL spec conformance — the validation rules ("Validation" section). Each rule
 * is checked with an invalid document (expects ≥1 error) and, where useful, a valid
 * one (expects none).
 */
final class ValidationConformanceTest extends TestCase
{
    private function schema(): Schema
    {
        $sdl = <<<'GRAPHQL'
        type Query {
          int: Int
          nested: Nested
          withArgs(a: Int!, b: Int): Int
          echoInput(in: MyInput!): String
          node: Node
        }
        type Nested { value: String }
        type Other { value: String }
        interface Node { id: ID! }
        type Thing implements Node { id: ID! }
        input MyInput { a: Int! b: String }
        GRAPHQL;

        return SchemaBuilder::fromSdl($sdl, ['Query' => ['int' => static fn () => 1]]);
    }

    private function errorCount(string $query): int
    {
        return count(DocumentValidator::validate($this->schema(), Parser::parse($query)));
    }

    private function assertInvalid(string $query): void
    {
        $this->assertGreaterThan(0, $this->errorCount($query), 'Expected a validation error for: '.$query);
    }

    private function assertValid(string $query): void
    {
        $this->assertSame(0, $this->errorCount($query), 'Expected no validation error for: '.$query);
    }

    public function test_field_existence(): void
    {
        $this->assertInvalid('{ notAField }');
        $this->assertValid('{ int }');
    }

    public function test_leaf_field_selections(): void
    {
        $this->assertInvalid('{ int { x } }');   // scalar cannot have a selection set
        $this->assertInvalid('{ nested }');       // composite must have a selection set
        $this->assertValid('{ nested { value } }');
    }

    public function test_argument_names(): void
    {
        $this->assertInvalid('{ withArgs(a: 1, bad: 2) }');
    }

    public function test_required_argument(): void
    {
        $this->assertInvalid('{ withArgs(b: 1) }');   // missing a: Int!
        $this->assertValid('{ withArgs(a: 1) }');
    }

    public function test_argument_type(): void
    {
        $this->assertInvalid('{ withArgs(a: "str") }');
    }

    public function test_values_of_correct_type_in_input_object(): void
    {
        $this->assertInvalid('{ echoInput(in: { a: "str" }) }');   // a must be Int
        $this->assertInvalid('{ echoInput(in: { b: "x" }) }');      // missing required a
        $this->assertValid('{ echoInput(in: { a: 1 }) }');
    }

    public function test_fragment_on_existing_type(): void
    {
        $this->assertInvalid('{ nested { value } } fragment F on Missing { value }');
    }

    public function test_fragment_spread_is_possible(): void
    {
        // Spreading an Other fragment on Nested can never match.
        $this->assertInvalid('{ nested { ...F } } fragment F on Other { value }');
    }

    public function test_unknown_fragment_spread(): void
    {
        $this->assertInvalid('{ nested { ...Undefined } }');
    }

    public function test_unused_fragment(): void
    {
        $this->assertInvalid('{ int } fragment F on Nested { value }');
    }

    public function test_fragment_cycle(): void
    {
        $this->assertInvalid('{ nested { ...A } } fragment A on Nested { ...B } fragment B on Nested { ...A }');
    }

    public function test_lone_anonymous_operation(): void
    {
        $this->assertInvalid('{ int } query Named { int }');
    }

    public function test_unique_operation_names(): void
    {
        $this->assertInvalid('query A { int } query A { int }');
    }

    public function test_variables_are_input_types(): void
    {
        $this->assertInvalid('query ($x: Node) { node { id } }');   // Node is not an input type
    }

    public function test_variable_defined_and_used(): void
    {
        $this->assertInvalid('{ withArgs(a: $undefined) }');            // used but not defined
        $this->assertInvalid('query ($x: Int) { int }');                // defined but not used
    }

    public function test_variable_position_type_compatibility(): void
    {
        // $x: Int (nullable, no default) used where Int! is required → invalid.
        $this->assertInvalid('query ($x: Int) { withArgs(a: $x) }');
        $this->assertValid('query ($x: Int!) { withArgs(a: $x) }');
    }

    public function test_directives_defined_and_located(): void
    {
        $this->assertInvalid('{ int @unknownDirective }');
        $this->assertValid('{ int @skip(if: true) }');
    }

    public function test_overlapping_fields_can_be_merged(): void
    {
        // Same response key "x" with conflicting underlying fields.
        $this->assertInvalid('{ x: int  x: nested { value } }');
    }
}
