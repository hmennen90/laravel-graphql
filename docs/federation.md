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

## Federation v2 directives

The subgraph SDL returned by `_service.sdl` is annotated for Federation **v2** — it
carries the `extend schema @link(...)` header and the federation directives, so an
Apollo gateway can compose it directly. Declare the directives per entity in the
config:

```php
Federation::subgraph($schema, [
    'User' => [
        'model'   => \App\Models\User::class,
        'resolve' => fn (array $ref) => \App\Models\User::find($ref['id']),
        'keys'      => ['id', 'email'],          // one or more @key selections
        'shareable' => ['displayName'],          // @shareable fields
        'external'  => ['legacyId'],             // @external fields
        'requires'  => ['fullName' => 'firstName lastName'], // @requires(fields:)
        'provides'  => ['account' => 'plan'],    // @provides(fields:)
    ],
]);
```

produces (excerpt):

```graphql
extend schema
  @link(url: "https://specs.apollo.dev/federation/v2.3", import: ["@key", "@shareable", "@external", "@requires", "@provides"])

type User @key(fields: "id") @key(fields: "email") {
  displayName: String @shareable
  legacyId: ID @external
  fullName: String @requires(fields: "firstName lastName")
}
```

`keys` defaults to `"id"` when omitted. The SDL is rendered by
`Hmennen90\GraphQL\Engine\Schema\SchemaPrinter` (the same printer behind
`graphql:print`); the federation plumbing (`_entities`, `_Service`, `_Any`) is
intentionally excluded from `_service.sdl`, per the federation spec.

> Federation is only relevant for a distributed **supergraph** composed of multiple
> subgraphs. For a single monolithic API you do not need it.
