<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\Eloquent\AllDirective;
use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

/**
 * @property int $id
 * @property string $title
 */
final class EloquentPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}

final class AllDirectiveTest extends TestCase
{
    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
    }

    public function test_all_directive_returns_every_model(): void
    {
        EloquentPost::create(['title' => 'First']);
        EloquentPost::create(['title' => 'Second']);

        $sdl = 'type Query { posts: [Post!]! @all(model: "'.addslashes(EloquentPost::class).'") }'
            .' type Post { id: ID! title: String }';

        $schema = SchemaBuilder::fromSdl($sdl, schemaDirectives: [
            'all' => new AllDirective(new ModelResolver()),
        ]);

        $result = Executor::execute($schema, Parser::parse('{ posts { id title } }'))->toArray();

        $this->assertSame([
            ['id' => '1', 'title' => 'First'],
            ['id' => '2', 'title' => 'Second'],
        ], $result['data']['posts']);
    }
}
