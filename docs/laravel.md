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

Subscriptions use Laravel broadcasting. Enable them in config:

```php
'subscriptions' => ['enabled' => true, 'broadcaster' => 'reverb'],
```

When a client sends a `subscription` operation, the endpoint registers a subscriber
and returns the channel to listen on:

```json
{ "data": null, "extensions": { "subscription": { "channel": "graphql.<id>" } } }
```

The client subscribes to that channel via Laravel Echo. When your application has
new data, broadcast it — the stored operation is re-executed against the payload and
pushed to every subscriber on that topic:

```php
app(\Hmennen90\GraphQL\Subscriptions\SubscriptionManager::class)
    ->broadcast('postAdded', $post);
```

The topic defaults to the subscription's root field name.
