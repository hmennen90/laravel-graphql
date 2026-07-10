<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

use Hmennen90\GraphQL\Console\PrintSchemaCommand;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Execution\ErrorHandler;
use Hmennen90\GraphQL\Http\Controllers\GraphiQLController;
use Hmennen90\GraphQL\Http\Controllers\GraphQLController;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\SubscriptionRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/graphql.php', 'graphql');

        $this->app->singleton(GraphQL::class, static function (Application $app): GraphQL {
            $config = $app->make(Repository::class);
            $graphqlConfig = $config->get('graphql', []);

            return new GraphQL($app, is_array($graphqlConfig) ? $graphqlConfig : []);
        });
        $this->app->alias(GraphQL::class, 'graphql');

        $this->app->singleton(Schema::class, static fn (Application $app): Schema => $app->make(GraphQL::class)->schema());

        $this->app->singleton(ErrorHandler::class, static function (Application $app): ErrorHandler {
            $config = $app->make(Repository::class);
            $safe = $config->get('graphql.safe_exceptions', []);

            return new ErrorHandler(
                (bool) $config->get('graphql.debug', false),
                array_values(array_filter(is_array($safe) ? $safe : [], 'is_string')),
            );
        });

        $this->app->singleton(ResponseBuilder::class);
        $this->app->singleton(SubscriptionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'graphql');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/graphql.php' => $this->app->configPath('graphql.php')], 'graphql-config');
            $this->publishes([__DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/graphql')], 'graphql-views');
            $this->commands([PrintSchemaCommand::class]);
        }
    }

    private function registerRoutes(): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('graphql.route.enabled') === true) {
            Route::middleware($this->stringList($config->get('graphql.route.middleware', [])))
                ->match(
                    $this->stringList($config->get('graphql.route.methods', ['GET', 'POST'])),
                    $this->string($config->get('graphql.route.uri'), '/graphql'),
                    [GraphQLController::class, 'handle'],
                )
                ->name($this->string($config->get('graphql.route.name'), 'graphql'));
        }

        if ($config->get('graphql.graphiql.enabled') === true) {
            Route::middleware($this->stringList($config->get('graphql.graphiql.middleware', ['web'])))
                ->get(
                    $this->string($config->get('graphql.graphiql.uri'), '/graphiql'),
                    [GraphiQLController::class, 'index'],
                )
                ->name($this->string($config->get('graphql.graphiql.name'), 'graphql.graphiql'));
        }
    }

    private function string(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
