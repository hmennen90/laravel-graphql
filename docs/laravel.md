# Laravel Integration

## Configuration

`config/graphql.php` controls the endpoint, GraphiQL, schema sources, batching,
error handling and subscriptions:

```php
return [
    'route' => ['uri' => '/graphql', 'middleware' => ['api']],
    'graphiql' => ['enabled' => true, 'uri' => '/graphiql'],
    'schema' => [
        'types' => [/* code-first type/resolver classes */],
        'sdl_path' => [/* paths to .graphql files */],
    ],
    'debug' => env('GRAPHQL_DEBUG', env('APP_DEBUG', false)),
];
```

## Endpoint

`POST /graphql` accepts a single operation or a batched array:

```json
{ "query": "{ hello }", "variables": {}, "operationName": null }
```

GraphQL errors are returned with HTTP 200 following the spec.

## Authorization

Route-level middleware comes from config. Field-level authorization is exposed to
resolvers through the execution context, delegating to Laravel Gates/policies.

## Validation

Argument validation integrates with Laravel's validator; failures surface under
`extensions.validation` in the error response.

## Subscriptions

The engine produces an event source for subscription operations; the Laravel layer
bridges it to broadcasting (Reverb/Pusher). Full WebSocket transport is a later
milestone.
