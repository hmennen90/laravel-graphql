<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class RelUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<RelPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(RelPost::class, 'user_id');
    }
}

final class RelPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<RelUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(RelUser::class, 'user_id');
    }
}

final class RelationDirectiveTest extends TestCase
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
        DbSchema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('user_id');
        });

        $ada = RelUser::create(['name' => 'Ada']);
        $grace = RelUser::create(['name' => 'Grace']);
        RelPost::create(['title' => 'A1', 'user_id' => $ada->id]);
        RelPost::create(['title' => 'A2', 'user_id' => $ada->id]);
        RelPost::create(['title' => 'G1', 'user_id' => $grace->id]);
    }

    public function test_relations_resolve(): void
    {
        $sdl = 'type Query { users: [User!]! @all(model: "'.addslashes(RelUser::class).'") }'
            .' type User { id: ID! name: String posts: [Post!]! @hasMany postsCount: Int @count(relation: "posts") }'
            .' type Post { id: ID! title: String author: User @belongsTo(relation: "user") }';

        $schema = SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());

        $result = Executor::execute($schema, Parser::parse(
            '{ users { name postsCount posts { title author { name } } } }',
        ))->toArray();

        $users = $result['data']['users'];
        $this->assertSame('Ada', $users[0]['name']);
        $this->assertSame(2, $users[0]['postsCount']);
        $this->assertSame(['A1', 'A2'], array_column($users[0]['posts'], 'title'));
        $this->assertSame('Ada', $users[0]['posts'][0]['author']['name']);
        $this->assertSame(1, $users[1]['postsCount']);
    }
}
