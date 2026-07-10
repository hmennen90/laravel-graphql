<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\TestCase;

final class GraphQLEndpointTest extends TestCase
{
    public function test_it_executes_a_query(): void
    {
        $this->postJson('/graphql', ['query' => '{ hello }'])
            ->assertOk()
            ->assertExactJson(['data' => ['hello' => 'world']]);
    }

    public function test_it_passes_arguments(): void
    {
        $this->postJson('/graphql', ['query' => '{ echo(msg: "hi") }'])
            ->assertOk()
            ->assertExactJson(['data' => ['echo' => 'hi']]);
    }

    public function test_it_resolves_nested_objects(): void
    {
        $this->postJson('/graphql', ['query' => '{ me { id name } }'])
            ->assertOk()
            ->assertExactJson(['data' => ['me' => ['id' => '1', 'name' => 'Ada']]]);
    }

    public function test_graphql_errors_return_http_200(): void
    {
        $this->postJson('/graphql', ['query' => '{ nope }'])
            ->assertOk()
            ->assertJsonStructure(['errors' => [['message']]]);
    }

    public function test_it_handles_batched_queries(): void
    {
        $this->postJson('/graphql', [
            ['query' => '{ hello }'],
            ['query' => '{ echo(msg: "yo") }'],
        ])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.data.hello', 'world')
            ->assertJsonPath('1.data.echo', 'yo');
    }

    public function test_authorization_failure_surfaces_a_client_safe_error(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ secret }'])->assertOk();

        $response->assertJsonPath('data.secret', null);
        $response->assertJsonPath('errors.0.extensions.category', 'authorization');
        $this->assertStringContainsString('Not authorized', $response->json('errors.0.message'));
    }

    public function test_missing_query_is_rejected(): void
    {
        $this->postJson('/graphql', [])
            ->assertOk()
            ->assertJsonStructure(['errors' => [['message']]]);
    }

    public function test_graphiql_route_is_served(): void
    {
        $this->get('/graphiql')
            ->assertOk()
            ->assertSee('GraphiQL', false);
    }
}
