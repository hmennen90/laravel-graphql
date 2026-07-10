<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Building;

use Hmennen90\GraphQL\Engine\Building\CodeFirst\SchemaBuilder as CodeFirstBuilder;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder as SdlBuilder;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderTest extends TestCase
{
    private const string SDL = <<<'GRAPHQL'
        type Query {
          hello: String!
          add(a: Int!, b: Int! = 0): Int!
        }
        GRAPHQL;

    public function test_sdl_builds_a_schema(): void
    {
        $schema = SdlBuilder::fromSdl(self::SDL, resolvers: [
            'Query' => [
                'hello' => fn (): string => 'world',
                'add' => fn ($root, array $args): int => $args['a'] + $args['b'],
            ],
        ]);

        $query = $schema->getQueryType();
        $this->assertNotNull($query);
        $this->assertSame('Query', $query->name());
        $this->assertSame('String!', (string) $query->getField('hello')->getType());
        $this->assertSame('Int!', (string) $query->getField('add')->getType());

        $add = $query->getField('add');
        $this->assertSame('Int!', (string) ($add->getArg('a')?->getType()));
        $this->assertTrue($add->getArg('b')?->hasDefaultValue());
        $this->assertSame(0, $add->getArg('b')?->getDefaultValue());
        $this->assertNotNull($add->getResolver());
    }

    public function test_sdl_and_code_first_are_structurally_equivalent(): void
    {
        $sdlSchema = SdlBuilder::fromSdl(self::SDL, resolvers: [
            'Query' => ['hello' => fn (): string => 'world', 'add' => fn (): int => 0],
        ]);

        $codeSchema = new Schema(new CodeFirstBuilder()
            ->query(new ObjectType('Query', [
                FieldDefinition::make('hello', Type::nonNull(Type::string())),
                FieldDefinition::make('add', Type::nonNull(Type::int()), args: [
                    Argument::make('a', Type::nonNull(Type::int())),
                    Argument::withDefault('b', Type::nonNull(Type::int()), 0),
                ]),
            ]))
            ->config());

        $this->assertSame(
            $this->fingerprint($sdlSchema),
            $this->fingerprint($codeSchema),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(Schema $schema): array
    {
        $query = $schema->getQueryType();
        $this->assertNotNull($query);

        $fields = [];
        foreach ($query->fields() as $field) {
            $args = [];
            foreach ($field->args() as $arg) {
                $args[$arg->getName()] = (string) $arg->getType();
            }
            $fields[$field->getName()] = ['type' => (string) $field->getType(), 'args' => $args];
        }

        return ['query' => $query->name(), 'fields' => $fields];
    }
}
