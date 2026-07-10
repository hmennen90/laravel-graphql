<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Benchmark;

use Hmennen90\GraphQL\GraphQL;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DbSchema;

final class FilterUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

/**
 * End-to-end benchmark of the Eloquent directive layer (full stack: parse + validate +
 * directive resolution + Eloquent over sqlite). Not part of the default suite. Run:
 *   ./vendor/bin/phpunit tests/Benchmark/EloquentDirectiveBench.php
 */
final class EloquentDirectiveBench extends TestCase
{
    private const int ROWS = 200;

    private string $sdlFile = '';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $model = FilterUser::class;
        $this->sdlFile = sys_get_temp_dir().'/elo-bench-'.uniqid().'.graphql';
        file_put_contents($this->sdlFile,
            'type Query {'
            .'  all: [User!]! @all(model: "'.addslashes($model).'")'
            .'  filtered(name: String @eq): [User!]! @all(model: "'.addslashes($model).'")'
            .'  page(name: String @eq): [User!]! @paginate(type: PAGINATOR, model: "'.addslashes($model).'")'
            .'} type User { id: ID! name: String }');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlFile]);
    }

    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $rows = [];
        for ($i = 1; $i <= self::ROWS; $i++) {
            $rows[] = ['name' => 'User '.$i];
        }
        FilterUser::insert($rows);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlFile);
        parent::tearDown();
    }

    /**
     * @param  callable():void  $fn
     */
    private function median(callable $fn, int $iterations = 80, int $warmup = 10): float
    {
        for ($i = 0; $i < $warmup; $i++) {
            $fn();
        }
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $fn();
            $times[] = hrtime(true) - $start;
        }
        sort($times);

        return (float) $times[intdiv(count($times), 2)];
    }

    public function test_directive_layer_end_to_end(): void
    {
        $graphql = $this->app->make(GraphQL::class);

        $scenarios = [
            '@all (200 rows)' => '{ all { id name } }',
            '@all + @eq (1 match)' => '{ filtered(name: "User 5") { id name } }',
            '@paginate + @eq' => '{ page(name: "User 5", first: 15) { data { id name } paginatorInfo { total } } }',
        ];

        $lines = [];
        foreach ($scenarios as $label => $query) {
            $ms = $this->median(static fn () => $graphql->execute($query)) / 1_000_000;
            $lines[] = sprintf('  %-24s %8.3f ms', $label, $ms);
        }

        fwrite(STDERR, "\nEloquent directive layer (sqlite, full stack):\n".implode("\n", $lines)."\n\n");

        $this->assertTrue(true);
    }
}
