# Schema — SDL (schema-first)

Write your schema in the GraphQL Schema Definition Language and bind resolvers by
type and field name.

```graphql
type Query {
  user(id: ID!): User
}

type User {
  id: ID!
  name: String
}
```

```php
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;

$schema = SchemaBuilder::fromSdl($sdl, resolvers: [
    'Query' => [
        'user' => fn ($root, array $args) => User::find($args['id']),
    ],
]);
```

Fields without an explicit resolver fall back to a default resolver that reads
array keys, object properties or getter methods.

Both the SDL and code-first builders produce the **same** internal `Schema`, so
the validator and executor behave identically regardless of how you defined it.
