<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Validation;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;
use PHPUnit\Framework\TestCase;

final class DocumentValidatorTest extends TestCase
{
    private function schema(): Schema
    {
        return SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query {
              hello: String!
              user(id: ID!): User
              search(term: String!, limit: Int = 10): [User!]!
            }

            type Mutation {
              rename(id: ID!, name: String!): User
            }

            type Subscription {
              userUpdated: User
              other: String
            }

            type User {
              id: ID!
              name: String
              friends: [User!]
            }
            GRAPHQL);
    }

    /**
     * @return array<int, string>
     */
    private function validate(string $query): array
    {
        $errors = DocumentValidator::validate($this->schema(), Parser::parse($query));

        return array_map(static fn ($e): string => $e->getMessage(), $errors);
    }

    public function test_valid_query_has_no_errors(): void
    {
        $this->assertSame([], $this->validate('{ user(id: "1") { id name friends { id } } }'));
    }

    public function test_unknown_field(): void
    {
        $errors = $this->validate('{ nope }');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot query field "nope"', $errors[0]);
    }

    public function test_unknown_argument(): void
    {
        $errors = $this->validate('{ hello(x: 1) }');
        $this->assertStringContainsString('Unknown argument "x"', implode("\n", $errors));
    }

    public function test_missing_required_argument(): void
    {
        $errors = $this->validate('{ user { id } }');
        $this->assertStringContainsString('argument "id"', implode("\n", $errors));
    }

    public function test_argument_of_wrong_type(): void
    {
        $errors = $this->validate('{ search(term: 5) { id } }');
        $this->assertStringContainsString('term', implode("\n", $errors));
    }

    public function test_leaf_field_must_not_have_selection(): void
    {
        $errors = $this->validate('{ hello { x } }');
        $this->assertStringContainsString('must not have a selection', implode("\n", $errors));
    }

    public function test_composite_field_requires_selection(): void
    {
        $errors = $this->validate('{ user(id: "1") }');
        $this->assertStringContainsString('requires a selection', implode("\n", $errors));
    }

    public function test_undefined_variable(): void
    {
        $errors = $this->validate('query { user(id: $id) { id } }');
        $this->assertStringContainsString('$id', implode("\n", $errors));
    }

    public function test_unused_variable(): void
    {
        $errors = $this->validate('query ($x: ID!) { hello }');
        $this->assertStringContainsString('never used', implode("\n", $errors));
    }

    public function test_unknown_fragment_spread(): void
    {
        $errors = $this->validate('{ user(id: "1") { ...Missing } }');
        $this->assertStringContainsString('Unknown fragment "Missing"', implode("\n", $errors));
    }

    public function test_fragment_cycle(): void
    {
        $errors = $this->validate('
            { user(id: "1") { ...A } }
            fragment A on User { friends { ...B } }
            fragment B on User { friends { ...A } }
        ');
        $this->assertStringContainsString('cycle', strtolower(implode("\n", $errors)));
    }

    public function test_subscription_single_root_field(): void
    {
        $errors = $this->validate('subscription { userUpdated { id } other }');
        $this->assertStringContainsString('single', strtolower(implode("\n", $errors)));
    }

    public function test_valid_fragment_and_variables(): void
    {
        $this->assertSame([], $this->validate('
            query ($id: ID!) { user(id: $id) { ...F } }
            fragment F on User { id name }
        '));
    }
}
