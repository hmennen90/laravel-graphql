<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Tests\TestCase;

final class CommandsTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('graphql.schema.factory', static fn () => SchemaBuilder::fromSdl(
            'type Query { hello: String users: [User!]! } type User { id: ID! name: String }',
        ));
    }

    public function test_validate_command_passes_for_a_valid_schema(): void
    {
        $this->artisan('graphql:validate')
            ->expectsOutputToContain('valid')
            ->assertSuccessful();
    }

    public function test_print_command_writes_sdl_to_a_file(): void
    {
        $path = sys_get_temp_dir().'/graphql-schema-'.uniqid().'.graphql';

        $this->artisan('graphql:print', ['--write' => $path])->assertSuccessful();

        $this->assertFileExists($path);
        $sdl = (string) file_get_contents($path);
        $this->assertStringContainsString('type User', $sdl);
        @unlink($path);
    }

    public function test_make_type_command_generates_a_class(): void
    {
        $file = $this->app->basePath('app/GraphQL/Types/WidgetType.php');
        @unlink($file);

        $this->artisan('make:graphql-type', ['name' => 'WidgetType'])->assertSuccessful();

        $this->assertFileExists($file);
        $this->assertStringContainsString('#[GraphQLType]', (string) file_get_contents($file));
        @unlink($file);
    }

    public function test_make_directive_command_generates_a_class(): void
    {
        $file = $this->app->basePath('app/GraphQL/Directives/UppercaseDirective.php');
        @unlink($file);

        $this->artisan('make:graphql-directive', ['name' => 'UppercaseDirective'])->assertSuccessful();

        $this->assertFileExists($file);
        $this->assertStringContainsString('implements SchemaDirective', (string) file_get_contents($file));
        @unlink($file);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function generators(): array
    {
        return [
            'scalar' => ['make:graphql-scalar', 'app/GraphQL/Scalars/DateType.php', 'extends ScalarType'],
            'query' => ['make:graphql-query', 'app/GraphQL/Queries/FetchUser.php', '__invoke'],
            'mutation' => ['make:graphql-mutation', 'app/GraphQL/Mutations/CreateUser.php', '__invoke'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('generators')]
    public function test_generator_creates_expected_class(string $command, string $relative, string $needle): void
    {
        $file = $this->app->basePath($relative);
        @unlink($file);

        $name = pathinfo($relative, PATHINFO_FILENAME);
        $this->artisan($command, ['name' => $name])->assertSuccessful();

        $this->assertFileExists($file);
        $this->assertStringContainsString($needle, (string) file_get_contents($file));
        @unlink($file);
    }
}
