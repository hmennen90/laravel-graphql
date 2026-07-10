<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Engine\Executor;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class IntrospectionTest extends TestCase
{
    private function schema(): Schema
    {
        return SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query {
              me: User
            }

            type User {
              id: ID!
              name: String
            }
            GRAPHQL, resolvers: ['Query' => ['me' => fn () => null]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function exec(string $query): array
    {
        return Executor::execute($this->schema(), Parser::parse($query))->toArray();
    }

    public function test_schema_meta_field(): void
    {
        $result = $this->exec('{ __schema { queryType { name } } }');
        $this->assertSame('Query', $result['data']['__schema']['queryType']['name']);
    }

    public function test_schema_lists_types(): void
    {
        $result = $this->exec('{ __schema { types { name kind } } }');
        $names = array_column($result['data']['__schema']['types'], 'name');
        $this->assertContains('User', $names);
        $this->assertContains('String', $names);
    }

    public function test_type_meta_field(): void
    {
        $result = $this->exec('{ __type(name: "User") { name kind fields { name } } }');
        $type = $result['data']['__type'];
        $this->assertSame('User', $type['name']);
        $this->assertSame('OBJECT', $type['kind']);
        $this->assertSame(['id', 'name'], array_column($type['fields'], 'name'));
    }

    public function test_field_type_of_type(): void
    {
        $result = $this->exec('{ __type(name: "User") { fields { name type { kind ofType { name } } } } }');
        $idField = $result['data']['__type']['fields'][0];
        $this->assertSame('id', $idField['name']);
        $this->assertSame('NON_NULL', $idField['type']['kind']);
        $this->assertSame('ID', $idField['type']['ofType']['name']);
    }
}
