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
                'keys' => 'id',
                'shareable' => ['name'],
            ],
        ]);
    }

    public function test_service_field_exposes_the_federated_subgraph_sdl(): void
    {
        $sdl = Executor::execute($this->subgraph(), Parser::parse('{ _service { sdl } }'))->toArray()['data']['_service']['sdl'];

        $this->assertStringContainsString('type User @key(fields: "id")', $sdl);
        $this->assertStringContainsString('name: String @shareable', $sdl);
        $this->assertStringContainsString('@link(url: "https://specs.apollo.dev/federation/v2.3"', $sdl);
        $this->assertStringContainsString('"@shareable"', $sdl);
        // federation plumbing types are not part of the developer-defined SDL
        $this->assertStringNotContainsString('_entities', $sdl);
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

    public function test_entities_returns_null_for_unknown_or_missing_typename(): void
    {
        $query = 'query ($r: [_Any!]!) { _entities(representations: $r) { __typename } }';

        $result = Executor::execute(
            $this->subgraph(),
            Parser::parse($query),
            variableValues: ['r' => [
                ['__typename' => 'Ghost', 'id' => '1'], // no resolver registered
                ['id' => '1'],                          // missing __typename
                ['__typename' => 'User', 'id' => '999'], // resolver runs but finds nothing
            ]],
        )->toArray();

        $this->assertSame([null, null, null], $result['data']['_entities']);
    }

    public function test_keys_are_derived_from_sdl_directives(): void
    {
        $sdl = 'type Query { ping: String } type User @key(fields: "id") @key(fields: "email") { id: ID! email: String legacy: ID @external }';
        $base = SchemaBuilder::fromSdl($sdl);

        $subgraph = Federation::subgraph(
            $base,
            ['User' => ['model' => FedUser::class, 'resolve' => static fn (array $r): ?FedUser => null]],
            \Hmennen90\GraphQL\Federation\FederationAnnotations::fromSdl($sdl),
        );

        $rendered = Executor::execute($subgraph, Parser::parse('{ _service { sdl } }'))->toArray()['data']['_service']['sdl'];

        $this->assertStringContainsString('@key(fields: "id") @key(fields: "email")', $rendered);
        $this->assertStringContainsString('legacy: ID @external', $rendered);
    }

    public function test_service_sdl_renders_all_federation_directives(): void
    {
        $base = SchemaBuilder::fromSdl('type Query { ping: String } type User { id: ID! email: String legacy: ID }');
        $subgraph = Federation::subgraph($base, [
            'User' => [
                'model' => FedUser::class,
                'resolve' => static fn (array $r): ?FedUser => null,
                'keys' => ['id', 'email'],
                'external' => ['legacy'],
                'requires' => ['email' => 'legacy'],
            ],
        ]);

        $sdl = Executor::execute($subgraph, Parser::parse('{ _service { sdl } }'))->toArray()['data']['_service']['sdl'];

        $this->assertStringContainsString('@key(fields: "id") @key(fields: "email")', $sdl);
        $this->assertStringContainsString('legacy: ID @external', $sdl);
        $this->assertStringContainsString('@requires(fields: "legacy")', $sdl);
        $this->assertStringContainsString('"@external"', $sdl);
        $this->assertStringContainsString('"@requires"', $sdl);
    }
}
