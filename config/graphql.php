<?php

declare(strict_types=1);

use Hmennen90\GraphQL\Exceptions\AuthorizationError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP endpoint
    |--------------------------------------------------------------------------
    */
    'route' => [
        'enabled' => true,
        'uri' => '/graphql',
        'name' => 'graphql',
        'middleware' => ['api'],
        'methods' => ['GET', 'POST'],
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphiQL in-browser IDE
    |--------------------------------------------------------------------------
    */
    'graphiql' => [
        'enabled' => env('GRAPHQL_GRAPHIQL', true),
        'uri' => '/graphiql',
        'name' => 'graphql.graphiql',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema source
    |--------------------------------------------------------------------------
    |
    | Provide either a code-first "factory" (a class implementing
    | Hmennen90\GraphQL\Contracts\ProvidesSchema, a Closure, or a Schema
    | instance) or one or more SDL files with a resolver map.
    |
    */
    'schema' => [
        'factory' => null,
        'sdl_path' => [],
        'resolvers' => [],

        // Cache the parsed SDL (AST) so the schema is not re-parsed on every boot.
        // Applies to SDL schemas only; write it with `php artisan graphql:cache`.
        'cache' => [
            'enabled' => env('GRAPHQL_SCHEMA_CACHE', false),
            'path' => storage_path('framework/cache/graphql-schema.cache'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error handling
    |--------------------------------------------------------------------------
    */
    'debug' => env('GRAPHQL_DEBUG', env('APP_DEBUG', false)),

    'safe_exceptions' => [
        AuthorizationError::class,
        AuthorizationException::class,
        AuthenticationException::class,
        ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query batching
    |--------------------------------------------------------------------------
    */
    'batching' => [
        'enabled' => true,
        'max' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query security limits (0 disables)
    |--------------------------------------------------------------------------
    */
    'security' => [
        'max_depth' => (int) env('GRAPHQL_MAX_DEPTH', 0),
        'max_complexity' => (int) env('GRAPHQL_MAX_COMPLEXITY', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Persisted Queries
    |--------------------------------------------------------------------------
    */
    'persisted_queries' => [
        'enabled' => env('GRAPHQL_PERSISTED_QUERIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache-Control header from @cacheControl hints
    |--------------------------------------------------------------------------
    */
    'cache_control' => [
        'enabled' => env('GRAPHQL_CACHE_CONTROL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent model resolution (for @all/@find/@paginate/relations)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'namespace' => 'App\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination defaults for @paginate
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_count' => 15,
        'max_count' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions (v1: broadcasting seam)
    |--------------------------------------------------------------------------
    */
    'subscriptions' => [
        'enabled' => false,
        'broadcaster' => env('GRAPHQL_SUBSCRIPTION_BROADCASTER', 'reverb'),
        // 'redis' fans events out to the graphql-ws server via Redis pub/sub.
        'driver' => env('GRAPHQL_SUBSCRIPTION_DRIVER', 'broadcast'),
    ],
];
