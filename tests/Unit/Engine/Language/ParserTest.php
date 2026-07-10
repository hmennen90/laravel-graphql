<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Language;

use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\ArgumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\DocumentNode;
use Hmennen90\GraphQL\Engine\Language\AST\FieldNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\FragmentSpreadNode;
use Hmennen90\GraphQL\Engine\Language\AST\InlineFragmentNode;
use Hmennen90\GraphQL\Engine\Language\AST\IntValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\ListValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\NonNullTypeNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\OperationType;
use Hmennen90\GraphQL\Engine\Language\AST\StringValueNode;
use Hmennen90\GraphQL\Engine\Language\AST\VariableNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function test_it_parses_a_shorthand_query(): void
    {
        $doc = Parser::parse('{ hello }');

        $this->assertInstanceOf(DocumentNode::class, $doc);
        $this->assertCount(1, $doc->definitions);

        $op = $doc->definitions[0];
        $this->assertInstanceOf(OperationDefinitionNode::class, $op);
        $this->assertSame(OperationType::QUERY, $op->operation);
        $this->assertNull($op->name);

        $field = $op->selectionSet->selections[0];
        $this->assertInstanceOf(FieldNode::class, $field);
        $this->assertSame('hello', $field->name);
    }

    public function test_it_parses_named_operations_and_aliases(): void
    {
        $doc = Parser::parse('mutation Save { result: doSave }');
        $op = $doc->definitions[0];

        $this->assertSame(OperationType::MUTATION, $op->operation);
        $this->assertSame('Save', $op->name);

        $field = $op->selectionSet->selections[0];
        $this->assertSame('result', $field->alias);
        $this->assertSame('doSave', $field->name);
    }

    public function test_it_parses_arguments_and_variables(): void
    {
        $doc = Parser::parse('query ($id: ID!) { user(id: $id, active: true) { name } }');
        $op = $doc->definitions[0];

        $varDef = $op->variableDefinitions[0];
        $this->assertInstanceOf(VariableNode::class, $varDef->variable);
        $this->assertSame('id', $varDef->variable->name);
        $this->assertInstanceOf(NonNullTypeNode::class, $varDef->type);

        $field = $op->selectionSet->selections[0];
        $this->assertCount(2, $field->arguments);
        $this->assertInstanceOf(ArgumentNode::class, $field->arguments[0]);
        $this->assertSame('id', $field->arguments[0]->name);
        $this->assertInstanceOf(VariableNode::class, $field->arguments[0]->value);
    }

    public function test_it_parses_list_and_object_and_int_values(): void
    {
        $doc = Parser::parse('{ f(list: [1, 2], obj: { a: 1 }) }');
        $field = $doc->definitions[0]->selectionSet->selections[0];

        $list = $field->arguments[0]->value;
        $this->assertInstanceOf(ListValueNode::class, $list);
        $this->assertInstanceOf(IntValueNode::class, $list->values[0]);
        $this->assertSame('1', $list->values[0]->value);

        $obj = $field->arguments[1]->value;
        $this->assertInstanceOf(ObjectValueNode::class, $obj);
        $this->assertSame('a', $obj->fields[0]->name);
    }

    public function test_it_parses_fragments(): void
    {
        $doc = Parser::parse('
            query { ...userFields ... on User { extra } }
            fragment userFields on User { id name }
        ');

        $selections = $doc->definitions[0]->selectionSet->selections;
        $this->assertInstanceOf(FragmentSpreadNode::class, $selections[0]);
        $this->assertSame('userFields', $selections[0]->name);
        $this->assertInstanceOf(InlineFragmentNode::class, $selections[1]);
        $this->assertSame('User', $selections[1]->typeCondition->name);

        $fragment = $doc->definitions[1];
        $this->assertInstanceOf(FragmentDefinitionNode::class, $fragment);
        $this->assertSame('userFields', $fragment->name);
        $this->assertSame('User', $fragment->typeCondition->name);
    }

    public function test_it_parses_directives(): void
    {
        $doc = Parser::parse('{ hero @include(if: true) { name } }');
        $field = $doc->definitions[0]->selectionSet->selections[0];

        $this->assertCount(1, $field->directives);
        $this->assertSame('include', $field->directives[0]->name);
    }

    public function test_it_parses_block_string_values(): void
    {
        $doc = Parser::parse('{ f(text: """hi""") }');
        $value = $doc->definitions[0]->selectionSet->selections[0]->arguments[0]->value;

        $this->assertInstanceOf(StringValueNode::class, $value);
        $this->assertSame('hi', $value->value);
        $this->assertTrue($value->block);
    }

    public function test_it_parses_sdl_object_type(): void
    {
        $doc = Parser::parse('
            "A person"
            type User implements Node {
                id: ID!
                name: String
                posts(first: Int = 10): [Post!]!
            }
        ');

        $type = $doc->definitions[0];
        $this->assertInstanceOf(ObjectTypeDefinitionNode::class, $type);
        $this->assertSame('User', $type->name);
        $this->assertSame('A person', $type->description);
        $this->assertSame('Node', $type->interfaces[0]->name);
        $this->assertCount(3, $type->fields);
        $this->assertSame('id', $type->fields[0]->name);
        $this->assertInstanceOf(NonNullTypeNode::class, $type->fields[0]->type);
        $this->assertSame('first', $type->fields[2]->arguments[0]->name);
    }

    public function test_it_reports_syntax_errors_with_location(): void
    {
        try {
            Parser::parse('{ foo(bar: }');
            $this->fail('Expected SyntaxError');
        } catch (SyntaxError $e) {
            $this->assertStringContainsString('Syntax Error', $e->getMessage());
            $this->assertNotEmpty($e->getLocations());
        }
    }

    public function test_it_rejects_empty_selection_set(): void
    {
        $this->expectException(SyntaxError::class);
        Parser::parse('{ }');
    }
}
