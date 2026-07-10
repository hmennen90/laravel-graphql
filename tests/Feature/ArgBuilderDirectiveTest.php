<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class ArgPost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @param  Builder<ArgPost>  $query
     * @return Builder<ArgPost>
     */
    public function scopeMinViews(Builder $query, int $min): Builder
    {
        return $query->where('views', '>=', $min);
    }
}

final class ArgBuilderDirectiveTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->integer('views');
        });

        ArgPost::insert([
            ['title' => 'Alpha', 'views' => 10],
            ['title' => 'Alpine', 'views' => 3],
            ['title' => 'Beta', 'views' => 8],
        ]);
    }

    private function schema(): Schema
    {
        $m = addslashes(ArgPost::class);
        $sdl = 'type Query {'
            .'  exact(title: String @eq): [Post!]! @all(model: "'.$m.'")'
            .'  search(q: String @like(key: "title")): [Post!]! @all(model: "'.$m.'")'
            .'  top(n: Int @limit): [Post!]! @all(model: "'.$m.'")'
            .'  popular(min: Int @scope(name: "minViews")): [Post!]! @all(model: "'.$m.'")'
            .'} type Post { id: ID! title: String views: Int }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: $this->app->make(DirectiveRegistry::class)->all());
    }

    /**
     * @return list<string>
     */
    private function titles(string $query): array
    {
        $data = Executor::execute($this->schema(), Parser::parse($query))->toArray()['data'];
        $field = array_key_first($data);

        return array_map(static fn (array $row): string => $row['title'], $data[$field]);
    }

    public function test_eq_filters_by_exact_value(): void
    {
        $this->assertSame(['Alpha'], $this->titles('{ exact(title: "Alpha") { title } }'));
    }

    public function test_like_filters_with_wildcards(): void
    {
        $this->assertSame(['Alpha', 'Alpine'], $this->titles('{ search(q: "Alp%") { title } }'));
    }

    public function test_limit_caps_the_result_count(): void
    {
        $this->assertCount(2, $this->titles('{ top(n: 2) { title } }'));
    }

    public function test_scope_applies_a_model_scope(): void
    {
        $this->assertSame(['Alpha', 'Beta'], $this->titles('{ popular(min: 8) { title } }'));
    }

    public function test_no_argument_means_no_constraint(): void
    {
        $this->assertCount(3, $this->titles('{ exact { title } }'));
    }
}
