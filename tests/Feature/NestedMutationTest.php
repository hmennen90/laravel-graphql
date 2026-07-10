<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class NestedUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<NestedPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(NestedPost::class, 'user_id');
    }
}

final class NestedPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<NestedUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(NestedUser::class, 'user_id');
    }
}

final class NestedMutationTest extends TestCase
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
            $table->foreignId('user_id')->nullable();
        });
    }

    private function schema(): Schema
    {
        $user = addslashes(NestedUser::class);
        $post = addslashes(NestedPost::class);
        $sdl = 'type Query { users: [User!]! @all(model: "'.$user.'") }'
            .' type Mutation {'
            .'   createUser(name: String!, posts: CreateUserPosts): User @create(model: "'.$user.'")'
            .'   createPost(title: String!, user: ConnectUser): Post @create(model: "'.$post.'")'
            .' }'
            .' input CreateUserPosts { create: [PostData!] }'
            .' input PostData { title: String! }'
            .' input ConnectUser { connect: ID }'
            .' type User { id: ID! name: String posts: [Post!]! @hasMany }'
            .' type Post { id: ID! title: String user: User @belongsTo }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());
    }

    public function test_nested_has_many_create(): void
    {
        $result = Executor::execute($this->schema(), Parser::parse(
            'mutation { createUser(name: "Ada", posts: { create: [{ title: "P1" }, { title: "P2" }] }) { name posts { title } } }',
        ))->toArray();

        $this->assertSame('Ada', $result['data']['createUser']['name']);
        $this->assertSame(['P1', 'P2'], array_column($result['data']['createUser']['posts'], 'title'));
    }

    public function test_nested_belongs_to_connect(): void
    {
        NestedUser::create(['name' => 'Grace']);

        $result = Executor::execute($this->schema(), Parser::parse(
            'mutation { createPost(title: "X", user: { connect: "1" }) { title user { name } } }',
        ))->toArray();

        $this->assertSame('X', $result['data']['createPost']['title']);
        $this->assertSame('Grace', $result['data']['createPost']['user']['name']);
    }
}
