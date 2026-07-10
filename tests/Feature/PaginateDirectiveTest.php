<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Directives\Eloquent\PaginateDirective;
use Hmennen90\GraphQL\Eloquent\ModelResolver;
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

/**
 * @property int $id
 */
final class PagePost extends Model
{
    protected $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}

final class PaginateDirectiveTest extends TestCase
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
        });

        foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
            PagePost::create(['title' => $title]);
        }
    }

    private function schema(string $type): Schema
    {
        $arg = $type === '' ? '' : ', type: "'.$type.'"';
        $sdl = 'type Query { posts: [Post!]! @paginate(model: "'.addslashes(PagePost::class).'"'.$arg.') }'
            .' type Post { id: ID! title: String }';

        return SchemaBuilder::fromSdl($sdl, schemaDirectives: ['paginate' => new PaginateDirective(new ModelResolver())]);
    }

    public function test_paginator_style(): void
    {
        $result = Executor::execute($this->schema('PAGINATOR'), Parser::parse(
            '{ posts(first: 2, page: 2) { data { id } paginatorInfo { currentPage lastPage total count hasMorePages } } }',
        ))->toArray();

        $page = $result['data']['posts'];
        $this->assertSame(['3', '4'], array_column($page['data'], 'id'));
        $this->assertSame(2, $page['paginatorInfo']['currentPage']);
        $this->assertSame(3, $page['paginatorInfo']['lastPage']);
        $this->assertSame(5, $page['paginatorInfo']['total']);
        $this->assertSame(2, $page['paginatorInfo']['count']);
        $this->assertTrue($page['paginatorInfo']['hasMorePages']);
    }

    public function test_connection_style(): void
    {
        $result = Executor::execute($this->schema('CONNECTION'), Parser::parse(
            '{ posts(first: 2) { edges { node { id } } pageInfo { hasNextPage } totalCount } }',
        ))->toArray();

        $conn = $result['data']['posts'];
        $this->assertSame(['1', '2'], array_column(array_column($conn['edges'], 'node'), 'id'));
        $this->assertTrue($conn['pageInfo']['hasNextPage']);
        $this->assertSame(5, $conn['totalCount']);
    }
}
