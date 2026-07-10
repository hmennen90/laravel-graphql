<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests;

use Hmennen90\GraphQL\GraphQLServiceProvider;
use Hmennen90\GraphQL\Tests\Fixtures\TestSchema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [GraphQLServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['GraphQL' => \Hmennen90\GraphQL\Facades\GraphQL::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('graphql.debug', true);
        $app['config']->set('graphql.schema.factory', TestSchema::class);
    }
}
