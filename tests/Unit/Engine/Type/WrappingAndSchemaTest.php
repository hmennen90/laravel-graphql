<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Type;

use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class WrappingAndSchemaTest extends TestCase
{
    public function test_wrapping_types_stringify(): void
    {
        $type = Type::nonNull(Type::listOf(Type::nonNull(Type::int())));

        $this->assertSame('[Int!]!', (string) $type);
        $this->assertSame('Int', Type::getNamedType($type)->name());
    }

    public function test_object_type_lazy_and_cyclic_fields(): void
    {
        $user = null;
        $post = null;

        $user = new ObjectType('User', function () use (&$post): array {
            /** @var ObjectType $post */
            return [
                FieldDefinition::make('name', Type::string()),
                FieldDefinition::make('bestPost', $post),
            ];
        });
        $post = new ObjectType('Post', function () use (&$user): array {
            /** @var ObjectType $user */
            return [
                FieldDefinition::make('title', Type::string()),
                FieldDefinition::make('author', $user),
            ];
        });

        $this->assertArrayHasKey('name', $user->fields());
        $this->assertSame('Post', $user->getField('bestPost')->getType()->name());
        $this->assertSame('User', $post->getField('author')->getType()->name());
    }

    public function test_schema_collects_types_and_roots(): void
    {
        $query = new ObjectType('Query', [
            FieldDefinition::make('hello', Type::string()),
        ]);

        $schema = new Schema(new SchemaConfig(query: $query));

        $this->assertSame($query, $schema->getQueryType());
        $this->assertNull($schema->getMutationType());
        $this->assertSame($query, $schema->getType('Query'));
        $this->assertNotNull($schema->getType('String'));
    }
}
