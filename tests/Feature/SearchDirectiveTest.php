<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\GraphQLServiceProvider;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;
use Laravel\Scout\Searchable;
use Laravel\Scout\ScoutServiceProvider;

final class SearchPost extends Model
{
    use Searchable;

    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return ['title' => $this->getAttribute('title')];
    }
}

final class SearchDirectiveTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [ScoutServiceProvider::class, GraphQLServiceProvider::class];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('scout.driver', 'database');
    }

    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });

        SearchPost::create(['title' => 'Hello world']);
        SearchPost::create(['title' => 'Goodbye moon']);
    }

    public function test_search_directive_finds_matching_models(): void
    {
        $sdl = 'type Query { search(term: String!): [Post!]! @search(model: "'.addslashes(SearchPost::class).'", by: "term") }'
            .' type Post { id: ID! title: String }';

        $schema = SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());

        $result = Executor::execute($schema, Parser::parse('{ search(term: "Hello") { title } }'))->toArray();

        $this->assertSame([['title' => 'Hello world']], $result['data']['search']);
    }
}
