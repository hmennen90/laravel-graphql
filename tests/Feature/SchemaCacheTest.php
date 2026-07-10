<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Engine\Schema\SchemaPrinter;
use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Tests\TestCase;

final class SchemaCacheTest extends TestCase
{
    private string $sdlPath = '';

    private string $cachePath = '';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sdlPath = sys_get_temp_dir().'/gql-sdl-'.uniqid().'.graphql';
        $this->cachePath = sys_get_temp_dir().'/gql-cache-'.uniqid().'.cache';
        file_put_contents($this->sdlPath, 'type Query { hi: String }');

        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlPath]);
        $app['config']->set('graphql.schema.cache', ['enabled' => true, 'path' => $this->cachePath]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlPath);
        @unlink($this->cachePath);
        parent::tearDown();
    }

    public function test_cache_command_writes_and_clear_removes_the_ast(): void
    {
        $this->artisan('graphql:cache')->assertSuccessful();
        $this->assertFileExists($this->cachePath);

        // The schema resolves through the cached AST.
        $sdl = SchemaPrinter::print($this->app->make(GraphQL::class)->schema());
        $this->assertStringContainsString('type Query', $sdl);

        $this->artisan('graphql:clear')->assertSuccessful();
        $this->assertFileDoesNotExist($this->cachePath);
    }
}
