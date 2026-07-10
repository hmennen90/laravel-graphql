# Getting Started

## Requirements

- PHP `^8.4`
- Laravel 11 or 12

## Installation

```bash
composer require hmennen90/laravel-graphql
```

The service provider is auto-discovered. Publish the config and a starter schema:

```bash
php artisan vendor:publish --tag=graphql-config
php artisan vendor:publish --tag=graphql-schema
```

By default the endpoint is served at `POST /graphql`, with GraphiQL at `/graphiql`.

## Your first query

```graphql
{
  hello
}
```

```json
{ "data": { "hello": "world" } }
```

See [Schema (code-first)](schema-code-first.md) and [Schema (SDL)](schema-sdl.md)
for how to define your own types, then [Laravel Integration](laravel.md) for the
endpoint, authorization and validation.
