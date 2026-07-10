<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Http\PersistedQueryException;
use Hmennen90\GraphQL\Http\PersistedQueryResolver;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;

final class SecurityTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.schema.factory', static fn () => SchemaBuilder::fromSdl(
            'type Query { hello: String }',
            ['Query' => ['hello' => static fn (): string => 'world']],
        ));
    }

    public function test_introspection_is_allowed_by_default(): void
    {
        $result = $this->app->make(GraphQL::class)->execute('{ __schema { queryType { name } } }')->toArray();
        $this->assertSame('Query', $result['data']['__schema']['queryType']['name']);
    }

    public function test_introspection_can_be_disabled(): void
    {
        $this->app['config']->set('graphql.security.disable_introspection', true);

        $result = $this->app->make(GraphQL::class)->execute('{ __schema { queryType { name } } }')->toArray();
        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('disabled', $result['errors'][0]['message']);

        // A normal query and __typename still work.
        $ok = $this->app->make(GraphQL::class)->execute('{ hello __typename }')->toArray();
        $this->assertSame('world', $ok['data']['hello']);
    }

    public function test_persisted_queries_only_rejects_raw_queries(): void
    {
        $this->app['config']->set('graphql.persisted_queries.enabled', true);
        $this->app['config']->set('graphql.persisted_queries.only', true);

        $resolver = new PersistedQueryResolver($this->app->make(Cache::class), $this->app->make(Config::class));

        $this->expectException(PersistedQueryException::class);
        $resolver->resolve('{ hello }', []);
    }
}
