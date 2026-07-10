# Eloquent directives

Build a CRUD API over Eloquent with SDL directives — no resolver code, and the
**model stays the single source of truth**. Directives are auto-registered; the
model is taken from a `model:` argument or the type name (convention
`config('graphql.models.namespace')`, default `App\Models`).

```graphql
type Query {
  users: [User!]! @all
  user(id: ID!): User @find
  posts: [Post!]! @paginate @whereConditions(columns: ["title"]) @orderBy(columns: ["id", "title"])
}

type Mutation {
  createUser(name: String!, email: String!): User @create
  updateUser(id: ID!, name: String): User @update
  deleteUser(id: ID!): User @delete
}

type User {
  id: ID!
  name: String
  posts: [Post!]! @hasMany
  postsCount: Int @count(relation: "posts")
}

type Post {
  id: ID!
  title: String
  author: User @belongsTo(relation: "user")
}
```

## Reading

| Directive | Effect |
|---|---|
| `@all` | all records of the model |
| `@find` | one record constrained by the field arguments |
| `@first` | first record matching the arguments |
| `@paginate(type: PAGINATOR\|CONNECTION)` | paginated list; generates the paginator/connection type and adds `first`/`page` (or `first`/`after`) arguments |

## Relations

`@hasMany`, `@hasOne`, `@belongsTo`, `@belongsToMany`, `@morphMany/@morphOne/@morphTo`
resolve the Eloquent relation named after the field (override with `relation:`).
`@count(relation:)` resolves a relation count.

## Filtering & sorting

- `@whereConditions(columns: [...])` adds a `where: [...]` argument. Each condition is
  `{ column, operator, value }` with operators `EQ/NEQ/GT/GTE/LT/LTE/LIKE/IN/NOT_IN`.
  Columns are restricted to the declared allow-list (a generated enum).
- `@orderBy(columns: [...])` adds an `orderBy: [{ column, order }]` argument (`ASC`/`DESC`).

Both compose with `@all` and `@paginate`, which apply them to the query.

### Single-argument filters

For simple filters, put a directive directly on an argument — the column defaults to
the argument name (override with `key:`). These apply to `@all`/`@paginate` queries:

```graphql
type Query {
  posts(
    title: String @like(key: "title")
    authorId: ID @eq
    tags: [String!] @in(key: "tag")
    minViews: Int @scope(name: "popular")
    limit: Int @limit
  ): [Post!]! @all
}
```

Available: `@eq`, `@neq`, `@in`, `@notIn`, `@like`, `@whereBetween`, `@whereNull`,
`@scope(name:)` (applies an Eloquent scope with the value) and `@limit`.

## Writing

`@create`, `@update`, `@delete`, `@upsert` — resolved from the field arguments, each in
a database transaction. `@update`/`@delete`/`@upsert` locate the record by `id` (override
with `key:`). For soft-delete models, `@forceDelete` permanently deletes and `@restore`
brings back a trashed record.

## Configuration

```php
'models' => ['namespace' => 'App\\Models'],
'pagination' => ['default_count' => 15, 'max_count' => 100],
```

## Search, auth & utilities

- `@search(by:)` — full-text search via Laravel Scout (requires `laravel/scout`).
- `@guard` — require an authenticated user; `@inject(context:, name:)` — inject a
  context value (e.g. the user id) into an argument.
- `@field(resolver:)` — bind a custom resolver class; `@rename(attribute:)` — read a
  differently-named source attribute.

The `graphql:print` Artisan command prints the built schema as SDL
(`Hmennen90\GraphQL\Engine\Schema\SchemaPrinter`).

## Validation & sanitisers

Argument directives — `@rules`, `@validator`, `@trim`, `@hash`, `@globalId` — validate
or sanitise input before the resolver runs and compose with the mutation directives.
See [Validation & argument sanitisers](validation.md).

## Code-first attributes

Every field directive has a PHP attribute equivalent under `Hmennen90\GraphQL\Attributes`
that dispatches to the **same** implementation — no duplicated logic:

```php
use Hmennen90\GraphQL\Attributes\{All, Find, First, Paginate, Guard, Count, Rename, Relation};

#[GraphQLType(name: 'Query')]
final class QueryType
{
    #[GraphQLField(type: '[User!]!')]
    #[All(model: User::class)]
    public function users(): array { return []; }

    #[GraphQLField(type: '[Post!]!')]
    #[Paginate(type: 'CONNECTION')]
    public function posts(): array { return []; }
}
```

Build with the directive registry so the attributes resolve:

```php
$directives = app(\Hmennen90\GraphQL\Directives\DirectiveRegistry::class)->all();
$types = (new AttributeSchemaBuilder($directives))->build([QueryType::class, UserType::class]);
```

`#[Relation(type: "hasMany", relation: "posts")]` covers the relation directives.

## Apollo Federation

Any schema can be exposed as a federated subgraph via `Federation::subgraph()`. See
[Apollo Federation](federation.md).
