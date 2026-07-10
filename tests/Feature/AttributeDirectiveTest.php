<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Attributes\All;
use Hmennen90\GraphQL\Attributes\Paginate;
use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLField;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLType;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\AttributeSchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class AttrUserModel extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

#[GraphQLType(name: 'User')]
final class AttrUserType
{
    #[GraphQLField(type: 'ID!')]
    public function id(mixed $source): string
    {
        return (string) (is_object($source) ? $source->id : '');
    }

    #[GraphQLField(type: 'String')]
    public function name(mixed $source): ?string
    {
        $value = is_object($source) ? $source->name : null;

        return is_string($value) ? $value : null;
    }
}

#[GraphQLType(name: 'Query')]
final class AttrQueryType
{
    /**
     * @return array<int, mixed>
     */
    #[GraphQLField(type: '[User!]!')]
    #[All(model: AttrUserModel::class)]
    public function users(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    #[GraphQLField(type: '[User!]!')]
    #[Paginate(type: 'PAGINATOR', model: AttrUserModel::class)]
    public function usersPaginated(): array
    {
        return [];
    }
}

final class AttributeDirectiveTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        AttrUserModel::create(['name' => 'Alice']);
        AttrUserModel::create(['name' => 'Bob']);
    }

    private function schema(): Schema
    {
        $directives = $this->app->make(DirectiveRegistry::class)->all();
        $types = new AttributeSchemaBuilder($directives)->build([AttrQueryType::class, AttrUserType::class]);

        return new Schema(new SchemaConfig(query: $types['Query'], types: array_values($types)));
    }

    public function test_all_attribute_dispatches_to_the_all_directive(): void
    {
        $result = Executor::execute($this->schema(), Parser::parse('{ users { name } }'))->toArray();

        $this->assertSame([['name' => 'Alice'], ['name' => 'Bob']], $result['data']['users']);
    }

    public function test_paginate_attribute_generates_a_paginator(): void
    {
        $query = '{ usersPaginated(first: 1, page: 1) { data { name } paginatorInfo { total currentPage } } }';

        $result = Executor::execute($this->schema(), Parser::parse($query))->toArray();

        $this->assertSame('Alice', $result['data']['usersPaginated']['data'][0]['name']);
        $this->assertSame(2, $result['data']['usersPaginated']['paginatorInfo']['total']);
        $this->assertSame(1, $result['data']['usersPaginated']['paginatorInfo']['currentPage']);
    }
}
