<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Executor;

use Hmennen90\GraphQL\Engine\Executor\DataLoader;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

final class DataLoaderTest extends TestCase
{
    public function test_dataloader_batches_nested_loads_into_one_call(): void
    {
        $batchCalls = [];

        $companies = [
            'c1' => ['id' => 'c1', 'name' => 'Acme'],
            'c2' => ['id' => 'c2', 'name' => 'Globex'],
        ];

        $loader = new DataLoader(function (array $keys) use (&$batchCalls, $companies): array {
            $batchCalls[] = $keys;

            return array_map(static fn (string $k): array => $companies[$k], $keys);
        });

        $company = new ObjectType('Company', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('name', Type::string()),
        ]);

        $user = new ObjectType('User', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('company', $company,
                resolve: fn (array $u) => $loader->load($u['companyId'])),
        ]);

        $query = new ObjectType('Query', [
            FieldDefinition::make('users', Type::nonNull(Type::listOf(Type::nonNull($user))),
                resolve: fn (): array => [
                    ['id' => '1', 'companyId' => 'c1'],
                    ['id' => '2', 'companyId' => 'c2'],
                    ['id' => '3', 'companyId' => 'c1'],
                ]),
        ]);

        $schema = new Schema(new SchemaConfig(query: $query));

        $result = Executor::execute($schema, Parser::parse('{ users { id company { name } } }'))->toArray();

        $this->assertSame([
            ['id' => '1', 'company' => ['name' => 'Acme']],
            ['id' => '2', 'company' => ['name' => 'Globex']],
            ['id' => '3', 'company' => ['name' => 'Acme']],
        ], $result['data']['users']);

        // exactly one batch call, with the two unique keys
        $this->assertCount(1, $batchCalls);
        $this->assertSame(['c1', 'c2'], $batchCalls[0]);
    }

    public function test_deferred_error_behaves_like_a_field_error(): void
    {
        $loader = new DataLoader(function (array $keys): array {
            throw new \RuntimeException('load failed');
        });

        $thing = new ObjectType('Thing', [
            FieldDefinition::make('name', Type::string(), resolve: fn () => $loader->load('x')),
        ]);
        $query = new ObjectType('Query', [
            FieldDefinition::make('thing', $thing, resolve: fn (): array => ['x' => 1]),
        ]);
        $schema = new Schema(new SchemaConfig(query: $query));

        $result = Executor::execute($schema, Parser::parse('{ thing { name } }'))->toArray();

        $this->assertNull($result['data']['thing']['name']);
        $this->assertArrayHasKey('errors', $result);
    }
}
