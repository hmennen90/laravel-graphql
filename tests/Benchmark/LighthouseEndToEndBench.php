<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Benchmark;

use Hmennen90\GraphQL\GraphQLServiceProvider;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema as DbSchema;
use Nuwave\Lighthouse\GraphQL as LighthouseGraphQL;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;

/**
 * End-to-end benchmark: full Laravel + Eloquent + directive resolution for BOTH
 * packages over the same sqlite table, via each package's GraphQL execution service
 * (this includes parse + validate + directive resolution + Eloquent, i.e. everything
 * an HTTP request does except the identical framework kernel/routing overhead).
 *
 * Not part of the default suite (lives outside tests/Unit and tests/Feature). Run:
 *   composer require --dev nuwave/lighthouse
 *   ./vendor/bin/phpunit tests/Benchmark/LighthouseEndToEndBench.php
 */
final class User extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

final class LighthouseEndToEndBench extends TestCase
{
    private const int ROWS = 200;

    private string $sdlFile = '';

    /**
     * @return array<int, class-string>
     */
    #[\Override]
    protected function getPackageProviders($app): array
    {
        $providers = [GraphQLServiceProvider::class];
        if (class_exists(LighthouseServiceProvider::class)) {
            $providers[] = LighthouseServiceProvider::class;
        }

        return $providers;
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sdlFile = sys_get_temp_dir().'/bench-schema-'.uniqid().'.graphql';
        file_put_contents($this->sdlFile, 'type Query { users: [User!]! @all } type User { id: ID! name: String email: String }');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

        // this package (override the base TestCase's default factory so sdl_path wins)
        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlFile]);
        $app['config']->set('graphql.models.namespace', __NAMESPACE__);

        // Lighthouse
        $app['config']->set('lighthouse.schema_path', $this->sdlFile);
        $app['config']->set('lighthouse.namespaces.models', [__NAMESPACE__]);
    }

    protected function defineDatabaseMigrations(): void
    {
        DbSchema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
        });

        $rows = [];
        for ($i = 1; $i <= self::ROWS; $i++) {
            $rows[] = ['name' => 'User '.$i, 'email' => 'u'.$i.'@example.test'];
        }
        User::insert($rows);
    }

    /**
     * @param  callable():void  $fn
     */
    private function median(callable $fn, int $iterations = 60, int $warmup = 8): float
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

    public function test_end_to_end_vs_lighthouse(): void
    {
        if (! class_exists(LighthouseGraphQL::class)) {
            $this->markTestSkipped('Install nuwave/lighthouse to run this benchmark.');
        }

        $query = '{ users { id name email } }';

        $ours = $this->app->make(\Hmennen90\GraphQL\GraphQL::class);
        $lighthouse = $this->app->make(LighthouseGraphQL::class);
        $context = $this->app->make(CreatesContext::class)->generate(Request::create('/graphql', 'POST'));

        // Correctness: both return the same number of rows.
        $oursResult = $ours->execute($query)->toArray();
        $lhResult = $lighthouse->executeQueryString($query, $context);
        $this->assertCount(self::ROWS, $oursResult['data']['users']);
        $this->assertCount(self::ROWS, $lhResult['data']['users']);

        $oursNs = $this->median(static fn () => $ours->execute($query));
        $lhNs = $this->median(static fn () => $lighthouse->executeQueryString($query, $context));

        $fmt = static fn (float $ns): string => sprintf('%.2f ms', $ns / 1_000_000);
        $ratio = $lhNs > 0 ? $oursNs / $lhNs : 0.0;
        $verdict = $ratio <= 1 ? sprintf('%.2fx faster', 1 / $ratio) : sprintf('%.2fx slower', $ratio);

        fwrite(STDERR, sprintf(
            "\nEnd-to-end (%d rows, `{ users { id name email } }`, sqlite):\n  laravel-graphql: %s\n  lighthouse:      %s\n  ratio: %s (ours/lighthouse)\n\n",
            self::ROWS,
            $fmt($oursNs),
            $fmt($lhNs),
            $verdict,
        ));

        $this->assertTrue(true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlFile);
        parent::tearDown();
    }
}
