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

### graphql-ws (WebSocket) transport

The package ships a spec-compliant [`graphql-ws`](https://github.com/enisdenjo/graphql-ws)
protocol handler (`connection_init`/`ack`, `subscribe`/`next`/`complete`, `ping`/`pong`),
`Hmennen90\GraphQL\Subscriptions\GraphqlWs\ProtocolHandler`. It is transport-agnostic —
drive it from any WebSocket server via the small `Connection` interface.

Because `cboden/ratchet` is incompatible with Laravel 12's Symfony 7, no WebSocket
server is bundled (keeping the package dependency-light). Wire the handler to a server
you control — for example OpenSwoole:

```php
$handler = new ProtocolHandler(app(GraphQL::class), app(ResponseBuilder::class));

$server = new Swoole\WebSocket\Server('0.0.0.0', 9501);
$server->on('message', function ($server, $frame) use ($handler) {
    $handler->onMessage(new SwooleConnection($server, $frame->fd), json_decode($frame->data, true));
});
// Redis pub/sub bridge: app calls broadcast -> WS server calls $handler->publish($topic, $event)
$server->start();
```

`SwooleConnection` is a thin adapter implementing `Connection` (`send`/`close`/`id`).
The event fan-out uses Redis pub/sub so multiple server processes stay in sync.
