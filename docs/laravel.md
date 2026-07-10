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

## Resolver conventions

A root `Query`/`Mutation` field resolves in this order: an explicit resolver map entry,
a directive (`@all`, `@field`, …), then **convention** — an invokable class
`<namespace>\<StudlyField>`:

```php
// config/graphql.php
'namespaces' => [
    'queries' => 'App\\GraphQL\\Queries',
    'mutations' => 'App\\GraphQL\\Mutations',
],
```

```graphql
type Query { latestPosts: [Post!]! }   # -> App\GraphQL\Queries\LatestPosts::__invoke
```

```php
namespace App\GraphQL\Queries;

final class LatestPosts
{
    public function __invoke(mixed $root, array $args, mixed $context, mixed $info): iterable
    {
        return \App\Models\Post::latest()->limit(10)->get();
    }
}
```

The class is resolved through the container (so dependencies are injected). If no such
class exists the field falls back to the default resolver. `make:graphql-query` /
`make:graphql-mutation` scaffold these.

## Artisan commands

```bash
php artisan graphql:print                 # print the schema as SDL
php artisan graphql:print --write         # write SDL to base_path/schema.graphql
php artisan graphql:print --write=docs/schema.graphql
php artisan graphql:validate              # validate the configured schema (CI guard)
php artisan graphql:lint                  # report unsupported directives (migration aid)
php artisan graphql:cache                 # cache the parsed SDL (AST) for faster boots
php artisan graphql:clear                 # drop the schema cache + persisted-query (APQ) entries
php artisan graphql:subscriptions:serve   # run the graphql-ws WebSocket server (Swoole)

php artisan make:graphql-type UserType                 # code-first object type
php artisan make:graphql-directive UppercaseDirective  # build-time directive
php artisan make:graphql-scalar DateType               # custom scalar
php artisan make:graphql-query FetchUser               # single-field query resolver (@field)
php artisan make:graphql-mutation CreateUser           # single-field mutation resolver (@field)
```

### Schema caching

For SDL schemas, cache the parsed AST so it is not re-parsed on every boot:

```php
// config/graphql.php
'schema' => [
    'cache' => [
        'enabled' => env('GRAPHQL_SCHEMA_CACHE', false),   // true in production
        'path'    => storage_path('framework/cache/graphql-schema.cache'),
    ],
],
```

Run `php artisan graphql:cache` on deploy and `php artisan graphql:clear` to invalidate
it. Caching applies to SDL schemas; factory/code-first schemas build directly.

`vendor:publish` exposes the config and views (and command stubs):

```bash
php artisan vendor:publish --tag=graphql-config
php artisan vendor:publish --tag=graphql-views
php artisan vendor:publish --tag=graphql-stubs   # customise make:graphql-* output
```

Published stubs land in `stubs/graphql/` and take precedence over the package's
built-in stubs.

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

## Generating types from your app

Derive GraphQL types from existing Laravel artifacts instead of re-declaring them:

- `Generation\ModelTypeGenerator::fromModel(User::class)` — object type from an Eloquent
  model's key, fillable attributes, casts and timestamps.
- `Generation\ValidationInputGenerator::fromRequest(StoreUserRequest::class)` /
  `->fromRules([...])` — input type from validation rules (`required` → non-null).
- `Generation\ResponseTypeGenerator::fromArray($resource->toArray($request), 'UserResource')`
  — object type inferred from a JSON resource / response shape (nested arrays → nested types).

`array`/`json` casts and `array` rules map to a bundled `JSON` scalar. See the README for a
full end-to-end example composing generated types into a schema.

## File uploads

Add the `Upload` scalar (`Hmennen90\GraphQL\Support\UploadType::make()`) to your
schema and send a [GraphQL multipart request](https://github.com/jaydenseric/graphql-multipart-request-spec)
(`operations` + `map` + files). Uploaded files arrive as `Illuminate\Http\UploadedFile`
instances in your resolver arguments.

## Persisted queries (APQ)

Enable Apollo Automatic Persisted Queries:

```php
'persisted_queries' => ['enabled' => true],
```

Clients send `extensions.persistedQuery.sha256Hash`; the first request registers the
query, subsequent requests may send the hash alone.

## Authorization directive

`@can(ability: "…")` gates a field behind a Laravel Gate ability, checked before
resolution. Register it as a schema directive:

```php
SchemaBuilder::fromSdl($sdl, $resolvers, schemaDirectives: [
    'can' => new \Hmennen90\GraphQL\Directives\CanDirective(),
]);
```

## Relay pagination

`Hmennen90\GraphQL\Support\Relay\Relay` builds `connection`/`edge`/`pageInfo` types and
cursor-based connection payloads from an array of nodes.

## HTTP caching (`@cacheControl`)

Annotate fields with `@cacheControl(maxAge: Int, scope: String)` and enable the header:

```php
'cache_control' => ['enabled' => true],
```

For a query, the endpoint emits `Cache-Control` using the **minimum** `maxAge` of the
selected fields and `private` if any field is private. A field without a hint makes the
response `no-store`. Combined with Automatic Persisted Queries (GET + hash), this enables
CDN/HTTP caching — the safe, idiomatic GraphQL approach (no per-user response store).

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

A bundled Swoole/OpenSwoole driver is available. With the extension installed, run:

```bash
php artisan graphql:subscriptions:serve --host=0.0.0.0 --port=9501
```

Set `graphql.subscriptions.driver` to `redis`; application code that calls
`SubscriptionManager::broadcast($topic, $event)` then publishes to Redis pub/sub, and the
server fans events out to connected clients. Without the extension the command exits with
an instruction to install it — the core package stays dependency-light.

### Server-Sent Events (SSE) transport

For a transport that needs **no WebSocket server or extension**, enable the graphql-sse
endpoint — it streams over plain HTTP:

```php
// config/graphql.php
'subscriptions' => [
    'sse' => ['enabled' => true, 'uri' => '/graphql/sse', 'middleware' => ['api']],
],
```

A query or mutation POSTed to the endpoint streams one `next` frame then `complete`. For
live subscriptions, bind `Hmennen90\GraphQL\Subscriptions\Sse\EventStream` to a
Redis-backed implementation whose `listen($topic)` yields events as they are published;
the controller writes each as a `next` frame until the client disconnects. The transport
core (`SseProtocolHandler`) is transport-agnostic and unit-tested.
