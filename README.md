# laravel-graphql

[![CI](https://github.com/hmennen90/laravel-graphql/actions/workflows/ci.yml/badge.svg)](https://github.com/hmennen90/laravel-graphql/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A **hand-written GraphQL engine** with first-class Laravel integration — no
dependency on `webonyx/graphql-php`. Define your schema **code-first** (PHP),
**schema-first** (SDL), or mix both; they compile to one internal schema.

> **Status:** in active development. See the [documentation](https://hmennen90.github.io/laravel-graphql/) for the current state.

## Features

- Own lexer, parser, AST, type system, validator and executor (spec-driven).
- **Hybrid schema**: code-first via PHP classes/attributes *and* schema-first via SDL.
- Built-in scalars (`Int`, `Float`, `String`, `Boolean`, `ID`) + custom scalars.
- Objects, interfaces, unions, enums, input objects, lists, non-null.
- Query validation, introspection (`__schema` / `__type`) for GraphiQL & Apollo tooling.
- Laravel: service provider, HTTP endpoint, batching, middleware/auth, argument
  validation, error masking, Artisan commands, GraphiQL, subscription seam.
- PHP 8.4, PHPStan level 10, tested with `orchestra/testbench`.

## Requirements

- PHP `^8.4`
- Laravel 11 or 12

## Installation

```bash
composer require hmennen90/laravel-graphql
```

Publish the config and a starter schema:

```bash
php artisan vendor:publish --tag=graphql-config
php artisan vendor:publish --tag=graphql-schema
```

## Quick start (code-first)

```php
use Hmennen90\GraphQL\Engine\Schema\Schema;
use Hmennen90\GraphQL\Engine\Schema\SchemaConfig;
use Hmennen90\GraphQL\Engine\Type\Definition\{FieldDefinition, ObjectType, Type};

$query = new ObjectType('Query', [
    FieldDefinition::make('hello', Type::nonNull(Type::string()),
        resolve: fn () => 'world'),
]);

$schema = new Schema(new SchemaConfig(query: $query));
```

## Quick start (schema-first)

```graphql
type Query {
  hello: String!
}
```

```php
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;

$schema = SchemaBuilder::fromSdl($sdl, resolvers: [
    'Query' => ['hello' => fn () => 'world'],
]);
```

## Documentation

Full documentation is published to **GitHub Pages**:
<https://hmennen90.github.io/laravel-graphql/>

## Contributing

Commits follow [Conventional Commits](https://www.conventionalcommits.org/); releases
are cut with semantic-release via a manual workflow dispatch.

```bash
composer test       # run the test suite
composer analyse    # PHPStan level 10
```

## License

MIT — see [LICENSE](LICENSE).
