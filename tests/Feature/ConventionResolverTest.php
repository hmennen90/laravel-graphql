<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Tests\TestCase;

final class Greeting
{
    public function __invoke(): string
    {
        return 'hello from convention';
    }
}

final class Ping
{
    public function __invoke(): string
    {
        return 'pong';
    }
}

final class ConventionResolverTest extends TestCase
{
    private string $sdlFile = '';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sdlFile = sys_get_temp_dir().'/conv-sdl-'.uniqid().'.graphql';
        file_put_contents($this->sdlFile, 'type Query { greeting: String } type Mutation { ping: String }');

        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlFile]);
        $app['config']->set('graphql.namespaces.queries', __NAMESPACE__);
        $app['config']->set('graphql.namespaces.mutations', __NAMESPACE__);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlFile);
        parent::tearDown();
    }

    public function test_query_field_resolves_by_convention_without_a_directive(): void
    {
        $result = $this->app->make(GraphQL::class)->execute('{ greeting }')->toArray();

        $this->assertSame('hello from convention', $result['data']['greeting']);
    }

    public function test_mutation_field_resolves_by_convention(): void
    {
        $result = $this->app->make(GraphQL::class)->execute('mutation { ping }')->toArray();

        $this->assertSame('pong', $result['data']['ping']);
    }
}
