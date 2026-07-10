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
 * End-to-end benchmark of this package vs Lighthouse across the shared directive set
 * (@all, @all+@eq, @paginate+@eq) — full Laravel + Eloquent over the same sqlite table,
 * through each package's GraphQL execution service. Both parse the identical SDL.
 *
 * Not part of the default suite. Run:
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
        if (! class_exists(LighthouseServiceProvider::class)) {
            return $providers;
        }

        // Testbench does not run package discovery, so register Lighthouse's feature
        // providers (pagination, etc.) explicitly — otherwise @paginate is unknown.
        $providers[] = LighthouseServiceProvider::class;
        foreach ([
            \Nuwave\Lighthouse\Pagination\PaginationServiceProvider::class,
            \Nuwave\Lighthouse\OrderBy\OrderByServiceProvider::class,
            \Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider::class,
            \Nuwave\Lighthouse\Validation\ValidationServiceProvider::class,
            \Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider::class,
        ] as $feature) {
            if (class_exists($feature)) {
                $providers[] = $feature;
            }
        }

        return $providers;
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sdlFile = sys_get_temp_dir().'/bench-schema-'.uniqid().'.graphql';
        file_put_contents($this->sdlFile,
            'type Query {'
            .'  users: [User!]! @all'
            .'  filtered(name: String @eq): [User!]! @all'
            .'  paginated(name: String @eq): [User!]! @paginate'
            .'} type User { id: ID! name: String email: String }');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

        $app['config']->set('graphql.schema.factory', null);
        $app['config']->set('graphql.schema.sdl_path', [$this->sdlFile]);
        $app['config']->set('graphql.models.namespace', __NAMESPACE__);

        $app['config']->set('lighthouse.schema_path', $this->sdlFile);
        $app['config']->set('lighthouse.namespaces.models', [__NAMESPACE__]);
        $app['config']->set('lighthouse.schema_cache.enable', false);
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

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->sdlFile);
        parent::tearDown();
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

        $ours = $this->app->make(\Hmennen90\GraphQL\GraphQL::class);
        $lighthouse = $this->app->make(LighthouseGraphQL::class);
        $context = $this->app->make(CreatesContext::class)->generate(Request::create('/graphql', 'POST'));

        $scenarios = [
            '@all (200 rows)' => '{ users { id name email } }',
            '@all + @eq (1 match)' => '{ filtered(name: "User 5") { id name } }',
            '@paginate + @eq' => '{ paginated(name: "User 5", first: 15) { data { id name } paginatorInfo { total } } }',
        ];

        $lines = [sprintf('  %-24s %12s %12s %10s', 'scenario', 'ours', 'lighthouse', 'ratio')];
        foreach ($scenarios as $label => $query) {
            // Correctness: both return a data payload.
            $this->assertArrayHasKey('data', $ours->execute($query)->toArray());
            $this->assertArrayHasKey('data', $lighthouse->executeQueryString($query, $context));

            $o = $this->median(static fn () => $ours->execute($query));
            $l = $this->median(static fn () => $lighthouse->executeQueryString($query, $context));
            $ratio = $l > 0 ? $o / $l : 0.0;
            $verdict = $ratio <= 1 ? sprintf('%.2fx fast', 1 / $ratio) : sprintf('%.2fx slow', $ratio);
            $lines[] = sprintf('  %-24s %9.3f ms %9.3f ms %10s', $label, $o / 1e6, $l / 1e6, $verdict);
        }

        fwrite(STDERR, "\nlaravel-graphql vs lighthouse (sqlite, full stack):\n".implode("\n", $lines)."\n\n");

        $this->assertTrue(true);
    }
}
