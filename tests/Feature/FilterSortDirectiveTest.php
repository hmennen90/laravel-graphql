<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class FiltPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}

final class FilterSortDirectiveTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->integer('views');
        });

        FiltPost::create(['title' => 'A', 'views' => 5]);
        FiltPost::create(['title' => 'B', 'views' => 20]);
        FiltPost::create(['title' => 'C', 'views' => 10]);
        FiltPost::create(['title' => 'D', 'views' => 30]);
    }

    public function test_where_conditions_and_order_by(): void
    {
        $sdl = 'type Query { posts: [Post!]! @all(model: "'.addslashes(FiltPost::class).'")'
            .' @whereConditions(columns: ["title", "views"]) @orderBy(columns: ["views", "id"]) }'
            .' type Post { id: ID! title: String views: Int }';

        $schema = SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());

        $result = Executor::execute($schema, Parser::parse(
            '{ posts(where: [{ column: views, operator: GTE, value: 10 }], orderBy: [{ column: views, order: DESC }]) { title views } }',
        ))->toArray();

        $this->assertSame(['D', 'B', 'C'], array_column($result['data']['posts'], 'title'));
        $this->assertSame([30, 20, 10], array_column($result['data']['posts'], 'views'));
    }
}
