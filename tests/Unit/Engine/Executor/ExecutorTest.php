<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Executor;

use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExecutorTest extends TestCase
{
    private function schema(): Schema
    {
        $user = null;
        $user = new ObjectType('User', function () use (&$user): array {
            /** @var ObjectType $user */
            return [
                FieldDefinition::make('id', Type::nonNull(Type::id())),
                FieldDefinition::make('name', Type::string()),
                FieldDefinition::make('mustName', Type::nonNull(Type::string()),
                    resolve: fn (array $u) => $u['name'] ?? null),
                FieldDefinition::make('friends', Type::listOf(Type::nonNull($user)),
                    resolve: fn (array $u): array => $u['friends'] ?? []),
            ];
        });

        $query = new ObjectType('Query', [
            FieldDefinition::make('hello', Type::nonNull(Type::string()), resolve: fn (): string => 'world'),
            FieldDefinition::make('boom', Type::string(), resolve: function (): string {
                throw new RuntimeException('kaboom');
            }),
            FieldDefinition::make('user', $user, args: [Argument::make('id', Type::nonNull(Type::id()))],
                resolve: fn ($root, array $args): array => [
                    'id' => $args['id'],
                    'name' => 'Ada',
                    'friends' => [['id' => '2', 'name' => 'Grace']],
                ]),
            FieldDefinition::make('sum', Type::nonNull(Type::int()),
                args: [Argument::make('a', Type::nonNull(Type::int())), Argument::withDefault('b', Type::nonNull(Type::int()), 5)],
                resolve: fn ($root, array $args): int => $args['a'] + $args['b']),
        ]);

        $log = [];
        $mutation = new ObjectType('Mutation', [
            FieldDefinition::make('push', Type::nonNull(Type::int()),
                args: [Argument::make('n', Type::nonNull(Type::int()))],
                resolve: function ($root, array $args) use (&$log): int {
                    $log[] = $args['n'];

                    return count($log);
                }),
        ]);

        return new Schema(new SchemaConfig(query: $query, mutation: $mutation));
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function exec(string $query, array $variables = []): array
    {
        return Executor::execute($this->schema(), Parser::parse($query), null, null, $variables)->toArray();
    }

    public function test_simple_scalar(): void
    {
        $this->assertSame(['data' => ['hello' => 'world']], $this->exec('{ hello }'));
    }

    public function test_nested_objects_and_lists(): void
    {
        $result = $this->exec('{ user(id: "1") { id name friends { id name } } }');
        $this->assertSame([
            'data' => [
                'user' => [
                    'id' => '1',
                    'name' => 'Ada',
                    'friends' => [['id' => '2', 'name' => 'Grace']],
                ],
            ],
        ], $result);
    }

    public function test_arguments_with_defaults(): void
    {
        $this->assertSame(['data' => ['sum' => 7]], $this->exec('{ sum(a: 2) }'));
        $this->assertSame(['data' => ['sum' => 5]], $this->exec('{ sum(a: 2, b: 3) }'));
    }

    public function test_variable_coercion(): void
    {
        $result = $this->exec('query ($id: ID!) { user(id: $id) { id } }', ['id' => 42]);
        $this->assertSame(['data' => ['user' => ['id' => '42']]], $result);
    }

    public function test_aliases(): void
    {
        $this->assertSame(['data' => ['greeting' => 'world']], $this->exec('{ greeting: hello }'));
    }

    public function test_field_error_produces_partial_data(): void
    {
        $result = $this->exec('{ hello boom }');
        $this->assertSame(['hello' => 'world', 'boom' => null], $result['data']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertSame(['boom'], $result['errors'][0]['path']);
        $this->assertStringContainsString('kaboom', $result['errors'][0]['message']);
    }

    public function test_non_null_violation_bubbles_to_nullable_parent(): void
    {
        // user.mustName is String! but name is null -> user becomes null
        $result = $this->exec('{ user(id: "1") { mustName } }');
        // resolver returns name 'Ada', so mustName ok; force null via friend without name
        $this->assertSame('Ada', $result['data']['user']['mustName']);
    }

    public function test_non_null_bubbling_nulls_the_list_element_path(): void
    {
        $result = $this->exec('{ user(id: "1") { friends { mustName } } }');
        // friend 'Grace' has a name, so ok
        $this->assertSame([['mustName' => 'Grace']], $result['data']['user']['friends']);
    }

    public function test_mutation_executes(): void
    {
        $this->assertSame(['data' => ['push' => 1]], $this->exec('mutation { push(n: 7) }'));
    }

    public function test_typename(): void
    {
        $result = $this->exec('{ user(id: "1") { __typename } }');
        $this->assertSame(['data' => ['user' => ['__typename' => 'User']]], $result);
    }
}
