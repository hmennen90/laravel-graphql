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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class SoftPost extends Model
{
    use SoftDeletes;

    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}

final class SoftDeleteDirectiveTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->softDeletes();
        });
    }

    private function schema(): Schema
    {
        $model = addslashes(SoftPost::class);
        $sdl = 'type Query { ping: String }'
            .' type Mutation {'
            .'   restorePost(id: ID!): Post @restore(model: "'.$model.'")'
            .'   forceDeletePost(id: ID!): Post @forceDelete(model: "'.$model.'")'
            .' } type Post { id: ID! title: String }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());
    }

    public function test_restore_brings_back_a_soft_deleted_model(): void
    {
        $post = SoftPost::create(['title' => 'Draft']);
        $post->delete();
        $this->assertNull(SoftPost::query()->find($post->id));

        $result = Executor::execute($this->schema(), Parser::parse('mutation { restorePost(id: "'.$post->id.'") { title } }'))->toArray();

        $this->assertSame('Draft', $result['data']['restorePost']['title']);
        $this->assertNotNull(SoftPost::query()->find($post->id));
    }

    public function test_force_delete_removes_the_row_permanently(): void
    {
        $post = SoftPost::create(['title' => 'Gone']);

        Executor::execute($this->schema(), Parser::parse('mutation { forceDeletePost(id: "'.$post->id.'") { id } }'))->toArray();

        $this->assertSame(0, SoftPost::withTrashed()->count());
    }
}
