<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Testing\MakesGraphQLRequests;
use Hmennen90\GraphQL\Tests\TestCase;

final class MakesGraphQLRequestsTest extends TestCase
{
    use MakesGraphQLRequests;

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.schema.factory', static fn () => SchemaBuilder::fromSdl(
            'type Query { greet(name: String!): String }',
            resolvers: ['Query' => ['greet' => static fn ($root, array $args): string => 'Hi '.$args['name']]],
        ));
    }

    public function test_graphql_helper_posts_to_the_endpoint(): void
    {
        $this->graphQL('query ($n: String!) { greet(name: $n) }', ['n' => 'Ada'])
            ->assertOk()
            ->assertJsonPath('data.greet', 'Hi Ada');
    }
}
