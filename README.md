# laravel-graphql

[![CI](https://github.com/hmennen90/laravel-graphql/actions/workflows/ci.yml/badge.svg)](https://github.com/hmennen90/laravel-graphql/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/hmennen90/laravel-graphql.svg)](https://packagist.org/packages/hmennen90/laravel-graphql)
[![PHP Version](https://img.shields.io/packagist/php-v/hmennen90/laravel-graphql.svg)](https://packagist.org/packages/hmennen90/laravel-graphql)
[![Total Downloads](https://img.shields.io/packagist/dt/hmennen90/laravel-graphql.svg)](https://packagist.org/packages/hmennen90/laravel-graphql)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A **hand-written GraphQL engine** with first-class Laravel integration — no
dependency on `webonyx/graphql-php`. Define your schema **code-first** (PHP),
**schema-first** (SDL), **attribute-driven**, or mix all three; they compile to
one internal schema.

> **Status:** in active development. APIs may change before the first stable release.

## Key differentiator — single source of truth

Unlike SDL-first stacks, you do **not** implement a type twice. There is no SDL
type mirroring your Eloquent model plus a separate type/transformer class, and no
directive DSL to keep in sync. Declare a type **once**; resolvers are plain PHP
callables that read your models directly.

## Features

- Own lexer, parser, AST, type system, validator and executor (spec-driven).
- **Hybrid schema:** code-first, SDL, and PHP attributes — one internal schema.
- Built-in scalars (`Int`, `Float`, `String`, `Boolean`, `ID`) + custom scalars.
- Objects, interfaces, unions, enums, input objects, lists, non-null.
- Comprehensive validation, introspection (GraphiQL/Apollo tooling), `@oneOf`, `@specifiedBy`.
- Custom directives (runtime middleware **and** build-time SDL), SDL type extensions.
- **DataLoader** (N+1 batching), query **depth/complexity limits**.
- Laravel: HTTP endpoint, batching, middleware/auth, error masking, GraphiQL,
  file uploads, Automatic Persisted Queries, `@cacheControl` HTTP caching,
  Relay pagination, subscriptions (broadcasting **+** graphql-ws).
- PHP 8.4, PHPStan level 10, tested with `orchestra/testbench`.

## Requirements

- PHP `^8.4`
- Laravel 11 or 12 (for the Laravel integration; the engine itself is framework-agnostic)

## Installation

```bash
composer require hmennen90/laravel-graphql
```

Publish the config (and optionally a starter schema):

```bash
php artisan vendor:publish --tag=graphql-config
```

---

## Quick start (Laravel)

Create a schema provider:

```php
<?php

namespace App\GraphQL;

use App\Models\User;
use Hmennen90\GraphQL\Contracts\ProvidesSchema;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

final class AppSchema implements ProvidesSchema
{
    public function schema(): Schema
    {
        $user = new ObjectType('User', [
            FieldDefinition::make('id', Type::nonNull(Type::id())),
            FieldDefinition::make('name', Type::string()),
            FieldDefinition::make('email', Type::string()),
        ]);

        $query = new ObjectType('Query', [
            FieldDefinition::make(
                'user',
                $user,
                args: [Argument::make('id', Type::nonNull(Type::id()))],
                resolve: fn ($root, array $args) => User::find($args['id']),
            ),
        ]);

        return new Schema(new SchemaConfig(query: $query));
    }
}
```

Register it in `config/graphql.php`:

```php
'schema' => [
    'factory' => App\GraphQL\AppSchema::class,
],
```

Query the endpoint:

```bash
curl -X POST http://localhost/graphql \
  -H 'Content-Type: application/json' \
  -d '{"query":"{ user(id: \"1\") { id name } }"}'
```

```json
{ "data": { "user": { "id": "1", "name": "Ada" } } }
```

---

## Defining a schema

### Code-first

```php
use Hmennen90\GraphQL\Engine\Type\Definition\{Argument, EnumType, EnumValueDefinition,
    FieldDefinition, InterfaceType, ObjectType, Type, UnionType};

$status = new EnumType('Status', [
    new EnumValueDefinition('ACTIVE'),
    new EnumValueDefinition('ARCHIVED', deprecationReason: 'Use ACTIVE'),
]);

$user = new ObjectType('User', [
    FieldDefinition::make('id', Type::nonNull(Type::id())),
    FieldDefinition::make('name', Type::string()),
    FieldDefinition::make('status', Type::nonNull($status)),
    // Lists & non-null wrappers:
    FieldDefinition::make('tags', Type::listOf(Type::nonNull(Type::string()))),
]);
```

Recursive/cyclic types are fine — pass a closure for lazily-resolved fields:

```php
$node = null;
$node = new ObjectType('Node', fn (): array => [
    FieldDefinition::make('id', Type::nonNull(Type::id())),
    FieldDefinition::make('parent', $node),
]);
```

### Schema-first (SDL)

```php
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;

$sdl = <<<'GRAPHQL'
type Query {
  user(id: ID!): User
}

type User {
  id: ID!
  name: String
}
GRAPHQL;

$schema = SchemaBuilder::fromSdl($sdl, resolvers: [
    'Query' => [
        'user' => fn ($root, array $args) => User::find($args['id']),
    ],
]);
```

Fields without a resolver fall back to reading array keys, object properties or getters.

### Attribute-driven

```php
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLField;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\Attributes\GraphQLType;
use Hmennen90\GraphQL\Engine\Building\CodeFirst\AttributeSchemaBuilder;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;

#[GraphQLType(name: 'Query')]
final class QueryType
{
    #[GraphQLField(type: 'String!')]
    public function hello(): string
    {
        return 'world';
    }

    #[GraphQLField(type: '[User!]!')]
    public function users(): array
    {
        return User::all()->all();
    }
}

$types = (new AttributeSchemaBuilder())->build([QueryType::class]);
$schema = new Schema(new SchemaConfig(query: $types['Query']));
```

### SDL type extensions

```graphql
type Query { hello: String! }
extend type Query { world: String! }
```

---

## Generating types from your Laravel app

The strongest form of "single source of truth": derive GraphQL types from the
artifacts you already maintain — Eloquent models, FormRequest rules and JSON
responses — instead of re-declaring their shape.

```php
use Hmennen90\GraphQL\Generation\ModelTypeGenerator;
use Hmennen90\GraphQL\Generation\ValidationInputGenerator;
use Hmennen90\GraphQL\Generation\ResponseTypeGenerator;

// 1. Object type from an Eloquent model (primary key, fillable, casts, timestamps)
$userType = (new ModelTypeGenerator())->fromModel(\App\Models\User::class);
//   -> type User { id: ID!  name: String  active: Boolean  meta: JSON  created_at: String ... }

// 2. Input type from a FormRequest's validation rules ("required" -> non-null)
$createUserInput = (new ValidationInputGenerator())
    ->fromRequest(\App\Http\Requests\StoreUserRequest::class, 'CreateUserInput');
//   from ['name' => 'required|string', 'age' => 'integer'] -> input CreateUserInput { name: String!  age: Int }

// ...or straight from a rules array:
$filterInput = (new ValidationInputGenerator())->fromRules([
    'term' => 'required|string',
    'limit' => 'integer',
], 'FilterInput');

// 3. Object type inferred from a JSON resource / response shape
$sample = (new \App\Http\Resources\UserResource($user))->toArray(request());
$userResourceType = (new ResponseTypeGenerator())->fromArray($sample, 'UserResource');
```

Compose the generated types into a schema like any hand-built type:

```php
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\{Argument, FieldDefinition, ObjectType, Type};

$query = new ObjectType('Query', [
    FieldDefinition::make('user', $userType,
        args: [Argument::make('id', Type::nonNull(Type::id()))],
        resolve: fn ($root, array $args) => \App\Models\User::find($args['id'])),
]);

$mutation = new ObjectType('Mutation', [
    FieldDefinition::make('createUser', $userType,
        args: [Argument::make('input', Type::nonNull($createUserInput))],
        resolve: fn ($root, array $args) => \App\Models\User::create($args['input'])),
]);

$schema = new Schema(new SchemaConfig(query: $query, mutation: $mutation, types: [$userResourceType]));
```

> Mapping notes: model casts and rule tokens map to the built-in scalars; `array`/`json`
> casts and `array` rules use a bundled `JSON` scalar. Nested resource arrays become nested
> object types. Generators produce a starting point you can refine — add relations, hide
> fields, or wrap the returned types as needed.

---

## Executing standalone (without Laravel)

The engine has no framework dependency:

```php
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;

$result = Executor::execute($schema, Parser::parse('{ user(id: "1") { id name } }'));

$result->toArray(); // ['data' => ['user' => ['id' => '1', 'name' => 'Ada']]]
```

Or validate first:

```php
use Hmennen90\GraphQL\Engine\Validation\DocumentValidator;

$errors = DocumentValidator::validate($schema, Parser::parse($query), [
    'maxDepth' => 10,
    'maxComplexity' => 100,
]);
```

---

## Laravel integration

### Configuration

`config/graphql.php` controls the endpoint, GraphiQL, schema source, batching,
error handling, security limits, persisted queries, cache-control and subscriptions.

### The `GraphQL` facade

```php
use Hmennen90\GraphQL\Facades\GraphQL;

$result = GraphQL::execute('{ user(id: "1") { name } }');
$schema = GraphQL::schema();
```

### Authorization

Inside a resolver, use the request `Context`:

```php
use Hmennen90\GraphQL\Execution\Context;

FieldDefinition::make('secret', Type::string(), resolve: function ($root, array $args, $context) {
    if ($context instanceof Context) {
        $context->authorize('view-secret'); // throws AuthorizationError on deny
    }

    return 'classified';
});
```

Or declaratively in SDL with the `@can` directive:

```php
use Hmennen90\GraphQL\Directives\CanDirective;

$schema = SchemaBuilder::fromSdl(<<<'GRAPHQL'
    directive @can(ability: String!) on FIELD_DEFINITION
    type Query {
      secret: String @can(ability: "view-secret")
    }
GRAPHQL, resolvers: [/* ... */], schemaDirectives: ['can' => new CanDirective()]);
```

### Argument validation

Use Laravel's validator inside a resolver; a thrown `ValidationException` surfaces
under `errors[].extensions.validation`:

```php
FieldDefinition::make('register', $user, args: [
    Argument::make('email', Type::nonNull(Type::string())),
], resolve: function ($root, array $args) {
    validator($args, ['email' => 'required|email'])->validate();

    return User::create($args);
});
```

### Error masking

With `graphql.debug = false`, internal exception messages are masked to
`"Internal server error"`. Client-safe exceptions (authorization, authentication,
validation) pass through and are categorised under `extensions.category`.

### File uploads

Add the `Upload` scalar and send a
[GraphQL multipart request](https://github.com/jaydenseric/graphql-multipart-request-spec):

```php
use Hmennen90\GraphQL\Support\UploadType;

$upload = UploadType::make();

FieldDefinition::make('import', Type::string(), args: [
    Argument::make('file', Type::nonNull($upload)),
], resolve: fn ($root, array $args) => $args['file']->getClientOriginalName());
```

Uploaded files arrive as `Illuminate\Http\UploadedFile` instances.

### Automatic Persisted Queries (APQ)

```php
// config/graphql.php
'persisted_queries' => ['enabled' => true],
```

Clients send `extensions.persistedQuery.sha256Hash`; the first request registers
the query, later requests may send the hash alone.

### HTTP caching (`@cacheControl`)

```php
// config/graphql.php
'cache_control' => ['enabled' => true],
```

```graphql
directive @cacheControl(maxAge: Int, scope: String) on FIELD_DEFINITION

type Query {
  articles: [Article!]! @cacheControl(maxAge: 60, scope: "PUBLIC")
}
```

The endpoint emits a `Cache-Control` header from the **minimum** `maxAge` of the
selected fields. Combined with APQ (GET + hash), this enables CDN/HTTP caching.

### Subscriptions

Enable and broadcast events:

```php
// config/graphql.php
'subscriptions' => ['enabled' => true],
```

```php
use Hmennen90\GraphQL\Subscriptions\SubscriptionManager;

app(SubscriptionManager::class)->broadcast('postAdded', $post);
```

Clients subscribe via Laravel Echo (broadcasting) or a `graphql-ws` client. To run
the bundled graphql-ws server (requires the Swoole extension):

```bash
php artisan graphql:subscriptions:serve --port=9501
```

---

## Performance

### DataLoader (N+1 batching)

```php
use Hmennen90\GraphQL\Engine\Executor\DataLoader;

$companies = new DataLoader(function (array $ids) {
    $byId = Company::findMany($ids)->keyBy('id');

    // must return one value per key, in the same order as $ids
    return array_map(fn ($id) => $byId->get($id), $ids);
});

FieldDefinition::make('company', $companyType,
    resolve: fn (array $user) => $companies->load($user['company_id']));
```

All companies requested during one query are fetched in a single batch call.

### Query limits

```php
// config/graphql.php
'security' => [
    'max_depth' => 15,
    'max_complexity' => 200,
],
```

---

## Custom directives

Runtime (query) directive — wrap field resolution:

```php
use Closure;
use Hmennen90\GraphQL\Engine\Executor\{DirectiveMiddleware, ResolveInfo};
use Hmennen90\GraphQL\Engine\Language\AST\DirectiveNode;

final class UpperDirective implements DirectiveMiddleware
{
    public function handle(DirectiveNode $node, ResolveInfo $info, Closure $resolve): mixed
    {
        $value = $resolve();

        return is_string($value) ? strtoupper($value) : $value;
    }
}

// register on the schema:
new SchemaConfig(query: $query, directiveMiddleware: ['upper' => new UpperDirective()]);
```

Build-time SDL directives implement `SchemaDirective` and are passed to
`SchemaBuilder::fromSdl(..., schemaDirectives: [...])` (see `@can`/`@cacheControl`).

## Relay pagination

```php
use Hmennen90\GraphQL\Support\Relay\Relay;

$connection = Relay::connectionType($userType);

FieldDefinition::make('users', $connection, args: [
    Argument::make('first', Type::int()),
    Argument::make('after', Type::string()),
], resolve: fn ($root, array $args) => Relay::connectionFromArray(User::all()->all(), $args));
```

## Introspection & GraphiQL

Full introspection is supported. With `graphql.graphiql.enabled = true`, an
in-browser IDE is served at `/graphiql`.

---

## Testing

```bash
composer test       # PHPUnit (Unit + Feature via orchestra/testbench)
composer analyse    # PHPStan level 10
```

## Contributing

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Commits follow
[Conventional Commits](https://www.conventionalcommits.org/); releases are cut with
semantic-release. Please also read the [Code of Conduct](CODE_OF_CONDUCT.md).

## Security

Please report security issues privately — see [SECURITY.md](SECURITY.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) (generated by semantic-release).

## Comparison

A full, per-package feature comparison (vs. Lighthouse, rebing/graphql-laravel,
webonyx/graphql-php) is in the
[documentation](https://hmennen90.github.io/laravel-graphql/comparison/).

## License

MIT — see [LICENSE](LICENSE).
