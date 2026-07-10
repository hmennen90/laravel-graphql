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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class MutUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

final class MutationDirectiveTest extends TestCase
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
            $table->string('email')->nullable();
        });
    }

    private function schema(): Schema
    {
        $model = addslashes(MutUser::class);
        $sdl = 'type Query { users: [User!]! @all(model: "'.$model.'") }'
            .' type Mutation {'
            .'   createUser(name: String!, email: String): User @create(model: "'.$model.'")'
            .'   updateUser(id: ID!, name: String): User @update(model: "'.$model.'")'
            .'   deleteUser(id: ID!): User @delete(model: "'.$model.'")'
            .' }'
            .' type User { id: ID! name: String email: String }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());
    }

    public function test_create_update_delete(): void
    {
        $schema = $this->schema();

        $created = Executor::execute($schema, Parser::parse('mutation { createUser(name: "Ada", email: "a@x.io") { id name email } }'))->toArray();
        $this->assertSame(['id' => '1', 'name' => 'Ada', 'email' => 'a@x.io'], $created['data']['createUser']);
        $this->assertSame(1, MutUser::query()->count());

        $updated = Executor::execute($schema, Parser::parse('mutation { updateUser(id: "1", name: "Ada Lovelace") { name } }'))->toArray();
        $this->assertSame('Ada Lovelace', $updated['data']['updateUser']['name']);
        $this->assertSame('Ada Lovelace', MutUser::query()->find(1)?->getAttribute('name'));

        $deleted = Executor::execute($schema, Parser::parse('mutation { deleteUser(id: "1") { id } }'))->toArray();
        $this->assertSame('1', $deleted['data']['deleteUser']['id']);
        $this->assertSame(0, MutUser::query()->count());
    }
}
