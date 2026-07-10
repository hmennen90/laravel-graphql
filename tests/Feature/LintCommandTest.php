<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\TestCase;

final class LintCommandTest extends TestCase
{
    private string $sdlFile = '';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->sdlFile = sys_get_temp_dir().'/lint-sdl-'.uniqid().'.graphql';
        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlFile]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlFile);
        parent::tearDown();
    }

    public function test_lint_passes_for_supported_directives(): void
    {
        file_put_contents($this->sdlFile, 'type Query { users: [User!]! @all } type User { id: ID! name: String @rename(attribute: "full_name") }');

        $this->artisan('graphql:lint')->expectsOutputToContain('Schema OK')->assertSuccessful();
    }

    public function test_lint_flags_unsupported_directives(): void
    {
        file_put_contents($this->sdlFile, 'type Query { users: [User!]! @all @scope(name: "active") } type User { id: ID! }');

        $this->artisan('graphql:lint')->assertFailed();
    }
}
