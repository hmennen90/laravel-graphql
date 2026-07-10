# Apollo Federation

Expose any schema as an [Apollo Federation](https://www.apollographql.com/docs/federation/)
subgraph. `Federation::subgraph()` takes a built schema and a map of entity reference
resolvers, and returns a new schema with the federation contract added:

- `_service { sdl }` — the subgraph SDL (rendered by the schema printer).
- `_entities(representations: [_Any!]!): [_Entity!]!` — resolves entity references.
- the generated `_Any` scalar, `_Service` type and `_Entity` union.

```php
use Hmennen90\GraphQL\Federation\Federation;

$subgraph = Federation::subgraph($schema, [
    'User' => [
        'model'   => \App\Models\User::class,
        'resolve' => fn (array $ref) => \App\Models\User::find($ref['id']),
    ],
    'Product' => [
        'model'   => \App\Models\Product::class,
        'resolve' => fn (array $ref) => \App\Models\Product::find($ref['id']),
    ],
]);
```

Wire it up as your schema via `graphql.schema.factory`:

```php
// config/graphql.php
'schema' => [
    'factory' => fn () => \Hmennen90\GraphQL\Federation\Federation::subgraph(
        app(MySchema::class)->schema(),
        [/* entity resolvers */],
    ),
],
```

## How entity resolution works

The gateway sends `_entities` a list of *representations* — each is an object with a
`__typename` and the type's key fields:

```graphql
query ($reps: [_Any!]!) {
  _entities(representations: $reps) {
    ... on User { id name }
  }
}
```

```json
{ "reps": [{ "__typename": "User", "id": "1" }] }
```

For each representation the subgraph looks up the resolver registered for its
`__typename`, calls it with the representation array, and resolves the returned model
into the matching object type (via the `_Entity` union). Representations whose type has
no resolver resolve to `null`.

## The `@key` directive & SDL

Mark entity types with `@key(fields: "id")` in your SDL so a gateway knows how to
reference them. The `_service.sdl` field returns the subgraph SDL, produced by
`Hmennen90\GraphQL\Engine\Schema\SchemaPrinter` — the same printer used by the
`graphql:print` Artisan command.

> Federation is only relevant for a distributed **supergraph** composed of multiple
> subgraphs. For a single monolithic API you do not need it.
