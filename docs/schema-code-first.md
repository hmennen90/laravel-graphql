# Schema — code-first

Build a schema from PHP objects. Types are lazy, so recursive/cyclic graphs can
be defined in any order.

```php
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\Argument;
use Hmennen90\GraphQL\Engine\Type\Definition\FieldDefinition;
use Hmennen90\GraphQL\Engine\Type\Definition\ObjectType;
use Hmennen90\GraphQL\Engine\Type\Definition\Type;

$user = new ObjectType('User', [
    FieldDefinition::make('id', Type::nonNull(Type::id())),
    FieldDefinition::make('name', Type::string()),
]);

$query = new ObjectType('Query', [
    FieldDefinition::make(
        'user',
        $user,
        resolve: fn ($root, array $args) => User::find($args['id']),
        args: [Argument::make('id', Type::nonNull(Type::id()))],
    ),
]);

$schema = new Schema(new SchemaConfig(query: $query));
```

## Wrapping types

```php
Type::nonNull(Type::string());              // String!
Type::listOf(Type::nonNull(Type::int()));   // [Int!]
Type::nonNull(Type::listOf(Type::id()));    // [ID]!
```

## Custom scalars, enums, interfaces & unions

`EnumType`, `InterfaceType` and `UnionType` are available under
`Hmennen90\GraphQL\Engine\Type\Definition`. Abstract types resolve their concrete
type via a `resolveType` callback.

## Attribute-driven types

You can also declare types with PHP attributes and let the reflection builder wire
the resolvers (each annotated method *is* the resolver):

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

    // Declare field arguments (built-in scalars) via `args`; they arrive coerced in $args.
    #[GraphQLField(type: 'String!', args: ['name' => 'String!', 'excited' => 'Boolean'])]
    public function greet(mixed $source, array $args): string
    {
        return 'Hi '.$args['name'];
    }
}

$types = (new AttributeSchemaBuilder())->build([QueryType::class]);
$schema = new Schema(new SchemaConfig(query: $types['Query']));
```

Type expressions accept the full GraphQL syntax (`String!`, `[User!]!`, …). Field
arguments are declared with `args: ['name' => 'typeExpression']` (built-in scalars).
