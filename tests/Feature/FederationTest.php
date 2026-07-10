<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Federation\Federation;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class FedUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

final class FederationTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        FedUser::create(['name' => 'Alice']);
    }

    private function subgraph(): \Hmennen90\GraphQL\Engine\Schema\Schema
    {
        $base = SchemaBuilder::fromSdl(<<<'GRAPHQL'
            type Query { ping: String }
            type User @key(fields: "id") { id: ID! name: String }
            GRAPHQL);

        return Federation::subgraph($base, [
            'User' => [
                'model' => FedUser::class,
                'resolve' => static fn (array $representation): ?FedUser => FedUser::find($representation['id'] ?? null),
            ],
        ]);
    }

    public function test_service_field_exposes_the_subgraph_sdl(): void
    {
        $result = Executor::execute($this->subgraph(), Parser::parse('{ _service { sdl } }'))->toArray();

        $this->assertStringContainsString('type User', $result['data']['_service']['sdl']);
    }

    public function test_entities_resolves_a_representation_to_a_model(): void
    {
        $query = 'query ($r: [_Any!]!) { _entities(representations: $r) { ... on User { id name } } }';

        $result = Executor::execute(
            $this->subgraph(),
            Parser::parse($query),
            variableValues: ['r' => [['__typename' => 'User', 'id' => '1']]],
        )->toArray();

        $this->assertSame([['id' => '1', 'name' => 'Alice']], $result['data']['_entities']);
    }
}
