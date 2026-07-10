<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL;

use Hmennen90\GraphQL\Console\CacheSchemaCommand;
use Hmennen90\GraphQL\Console\ClearCacheCommand;
use Hmennen90\GraphQL\Console\LintSchemaCommand;
use Hmennen90\GraphQL\Console\MakeDirectiveCommand;
use Hmennen90\GraphQL\Console\MakeMutationCommand;
use Hmennen90\GraphQL\Console\MakeQueryCommand;
use Hmennen90\GraphQL\Console\MakeScalarCommand;
use Hmennen90\GraphQL\Console\MakeTypeCommand;
use Hmennen90\GraphQL\Console\PrintSchemaCommand;
use Hmennen90\GraphQL\Console\SubscriptionServerCommand;
use Hmennen90\GraphQL\Console\ValidateSchemaCommand;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Execution\ErrorHandler;
use Hmennen90\GraphQL\Http\Controllers\GraphiQLController;
use Hmennen90\GraphQL\Http\Controllers\GraphQLController;
use Hmennen90\GraphQL\Http\PersistedQueryResolver;
use Hmennen90\GraphQL\Http\ResponseBuilder;
use Hmennen90\GraphQL\Subscriptions\CacheSubscriptionStore;
use Hmennen90\GraphQL\Subscriptions\EventPublisher;
use Hmennen90\GraphQL\Subscriptions\GraphqlWs\SubscriptionServer;
use Hmennen90\GraphQL\Subscriptions\NullEventPublisher;
use Hmennen90\GraphQL\Subscriptions\RedisEventPublisher;
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;
use Hmennen90\GraphQL\Subscriptions\SubscriptionStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GraphQLServiceProvider extends ServiceProvider
{
    #[\Override]
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
                array_values(array_filter(is_array($safe) ? $safe : [], is_string(...))),
            );
        });

        $this->app->singleton(ResponseBuilder::class);
        $this->app->singleton(PersistedQueryResolver::class, static fn (Application $app): PersistedQueryResolver => new PersistedQueryResolver(
            $app->make(CacheRepository::class),
            $app->make(Repository::class),
        ));

        $this->app->singleton(SubscriptionStore::class, static fn (Application $app): SubscriptionStore => new CacheSubscriptionStore($app->make(CacheRepository::class)));

        $this->app->singleton(EventPublisher::class, static function (Application $app): EventPublisher {
            $config = $app->make(Repository::class);
            if ($config->get('graphql.subscriptions.driver') === 'redis') {
                return new RedisEventPublisher($app->make(\Illuminate\Contracts\Redis\Factory::class));
            }

            return new NullEventPublisher();
        });

        $this->app->singleton(SubscriptionManager::class);

        $this->app->singleton(\Hmennen90\GraphQL\Eloquent\ModelResolver::class, static function (Application $app): \Hmennen90\GraphQL\Eloquent\ModelResolver {
            $config = $app->make(Repository::class);
            $namespace = $config->get('graphql.models.namespace', 'App\\Models');

            return new \Hmennen90\GraphQL\Eloquent\ModelResolver(is_string($namespace) ? $namespace : 'App\\Models');
        });

        $this->app->singleton(\Hmennen90\GraphQL\Directives\DirectiveRegistry::class, static function (Application $app): \Hmennen90\GraphQL\Directives\DirectiveRegistry {
            $registry = new \Hmennen90\GraphQL\Directives\DirectiveRegistry();
            $models = $app->make(\Hmennen90\GraphQL\Eloquent\ModelResolver::class);

            $config = $app->make(Repository::class);
            $default = $config->get('graphql.pagination.default_count', 15);
            $max = $config->get('graphql.pagination.max_count', 100);

            $registry->register('all', new \Hmennen90\GraphQL\Directives\Eloquent\AllDirective($models));
            $registry->register('find', new \Hmennen90\GraphQL\Directives\Eloquent\FindDirective($models));
            $registry->register('first', new \Hmennen90\GraphQL\Directives\Eloquent\FirstDirective($models));
            $registry->register('paginate', new \Hmennen90\GraphQL\Directives\Eloquent\PaginateDirective(
                $models,
                is_int($default) ? $default : 15,
                is_int($max) ? $max : 100,
            ));

            $relation = new \Hmennen90\GraphQL\Directives\Eloquent\RelationDirective();
            foreach (['hasMany', 'hasOne', 'belongsTo', 'belongsToMany', 'morphMany', 'morphOne', 'morphTo'] as $relationName) {
                $registry->register($relationName, $relation);
            }
            $registry->register('count', new \Hmennen90\GraphQL\Directives\Eloquent\CountDirective());
            $registry->register('orderBy', new \Hmennen90\GraphQL\Directives\Eloquent\OrderByDirective());
            $registry->register('whereConditions', new \Hmennen90\GraphQL\Directives\Eloquent\WhereConditionsDirective());

            $registry->register('guard', new \Hmennen90\GraphQL\Directives\GuardDirective());
            $registry->register('inject', new \Hmennen90\GraphQL\Directives\InjectDirective());
            $registry->register('rename', new \Hmennen90\GraphQL\Directives\RenameDirective());
            $registry->register('field', new \Hmennen90\GraphQL\Directives\FieldResolverDirective($app));

            // Argument sanitisers & validation.
            $registry->register('trim', new \Hmennen90\GraphQL\Directives\TrimDirective());
            $registry->register('hash', new \Hmennen90\GraphQL\Directives\HashDirective());
            $registry->register('globalId', new \Hmennen90\GraphQL\Directives\GlobalIdDirective());
            $registry->register('rules', new \Hmennen90\GraphQL\Directives\RulesDirective());
            $registry->register('validator', new \Hmennen90\GraphQL\Directives\ValidatorDirective($app));

            $registry->register('create', new \Hmennen90\GraphQL\Directives\Eloquent\CreateDirective($models));
            $registry->register('update', new \Hmennen90\GraphQL\Directives\Eloquent\UpdateDirective($models));
            $registry->register('delete', new \Hmennen90\GraphQL\Directives\Eloquent\DeleteDirective($models));
            $registry->register('upsert', new \Hmennen90\GraphQL\Directives\Eloquent\UpsertDirective($models));

            if (class_exists(\Laravel\Scout\Builder::class)) {
                $registry->register('search', new \Hmennen90\GraphQL\Directives\Eloquent\SearchDirective($models));
            }
            $registry->register('can', new \Hmennen90\GraphQL\Directives\CanDirective());
            $registry->register('cacheControl', new \Hmennen90\GraphQL\Directives\CacheControlDirective());

            return $registry;
        });

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            $this->app->bind(SubscriptionServer::class, \Hmennen90\GraphQL\Subscriptions\Swoole\SwooleSubscriptionServer::class);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'graphql');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/graphql.php' => $this->app->configPath('graphql.php')], 'graphql-config');
            $this->publishes([__DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/graphql')], 'graphql-views');
            $this->publishes([__DIR__.'/Console/stubs' => $this->app->basePath('stubs/graphql')], 'graphql-stubs');
            $this->commands([
                PrintSchemaCommand::class,
                ValidateSchemaCommand::class,
                LintSchemaCommand::class,
                CacheSchemaCommand::class,
                ClearCacheCommand::class,
                SubscriptionServerCommand::class,
                MakeTypeCommand::class,
                MakeDirectiveCommand::class,
                MakeScalarCommand::class,
                MakeQueryCommand::class,
                MakeMutationCommand::class,
            ]);
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
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
