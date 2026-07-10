<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Building;

use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLField;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLType;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\AttributeSchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use PHPUnit\Framework\TestCase;

#[GraphQLType(name: 'Query')]
final class AttributeQueryType
{
    #[GraphQLField(type: 'String!')]
    public function hello(): string
    {
        return 'world';
    }

    #[GraphQLField(type: 'Greeting!')]
    public function greeting(): array
    {
        return ['text' => 'hi'];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    #[GraphQLField(type: 'String!', args: ['name' => 'String!', 'excited' => 'Boolean'])]
    public function greet(mixed $source, array $args): string
    {
        return 'Hi '.$args['name'].(($args['excited'] ?? false) === true ? '!' : '');
    }
}

#[GraphQLType(name: 'Greeting')]
final class AttributeGreetingType
{
    /**
     * @param  array<string, mixed>  $source
     */
    #[GraphQLField(type: 'String!')]
    public function text(mixed $source): string
    {
        return is_array($source) ? (string) $source['text'] : '';
    }
}

final class AttributeSchemaBuilderTest extends TestCase
{
    public function test_it_builds_and_executes_an_attribute_schema(): void
    {
        $types = new AttributeSchemaBuilder()->build([AttributeQueryType::class, AttributeGreetingType::class]);
        $schema = new Schema(new SchemaConfig(query: $types['Query']));

        $result = Executor::execute($schema, Parser::parse('{ hello greeting { text } }'))->toArray();

        $this->assertSame([
            'data' => ['hello' => 'world', 'greeting' => ['text' => 'hi']],
        ], $result);
    }

    public function test_attribute_field_arguments_are_coerced_and_passed(): void
    {
        $types = new AttributeSchemaBuilder()->build([AttributeQueryType::class, AttributeGreetingType::class]);
        $schema = new Schema(new SchemaConfig(query: $types['Query']));

        $result = Executor::execute($schema, Parser::parse('{ greet(name: "Ada", excited: true) }'))->toArray();

        $this->assertSame('Hi Ada!', $result['data']['greet']);
    }
}
