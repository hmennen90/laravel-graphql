<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Schema;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\InterfaceType;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function errors(Schema $schema): array
    {
        return array_map(static fn ($e): string => $e->getMessage(), $schema->validate());
    }

    public function test_a_valid_schema_has_no_errors(): void
    {
        $schema = SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query { animal: Animal }
            interface Animal { sound: String }
            type Dog implements Animal { sound: String bark: String }
            GRAPHQL);

        $this->assertSame([], $schema->validate());
    }

    public function test_missing_query_type(): void
    {
        $errors = $this->errors(new Schema(new SchemaConfig()));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Query', implode("\n", $errors));
    }

    public function test_object_without_fields(): void
    {
        $empty = new ObjectType('Empty', []);
        $query = new ObjectType('Query', [FieldDefinition::make('e', $empty)]);

        $errors = $this->errors(new Schema(new SchemaConfig(query: $query)));

        $this->assertStringContainsString('must define one or more fields', implode("\n", $errors));
    }

    public function test_interface_not_fully_implemented(): void
    {
        $animal = new InterfaceType('Animal', [FieldDefinition::make('sound', Type::string())]);
        $dog = new ObjectType('Dog', [FieldDefinition::make('name', Type::string())], [$animal]);
        $query = new ObjectType('Query', [FieldDefinition::make('dog', $dog)]);

        $errors = $this->errors(new Schema(new SchemaConfig(query: $query, types: [$animal])));

        $this->assertStringContainsString('sound', implode("\n", $errors));
    }
}
