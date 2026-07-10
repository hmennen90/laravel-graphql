# laravel-graphql

A **hand-written GraphQL engine** with first-class Laravel integration — no
dependency on `webonyx/graphql-php`.

!!! warning "Status"
    This package is in active development. APIs may change until the first
    stable release.

## Why another GraphQL package?

- **Own engine** — lexer, parser, AST, type system, validator and executor are
  implemented from scratch, following the GraphQL specification algorithms.
- **Hybrid schema** — define types **code-first** in PHP (including attributes)
  or **schema-first** in SDL. Both compile to a single internal schema.
- **Laravel-native** — HTTP endpoint, batching, middleware/auth, argument
  validation, error masking, Artisan commands, GraphiQL and a subscription seam.
- **Strict quality** — PHP 8.4, PHPStan level 10, `orchestra/testbench`, and a
  [GraphQL spec conformance suite](conformance.md).

## Feature overview

| Area | Support |
|------|---------|
| Scalars | `Int`, `Float`, `String`, `Boolean`, `ID` + custom |
| Types | Object, Interface, Union, Enum, Input, List, Non-Null |
| Directives | `@skip`, `@include`, `@deprecated` |
| Introspection | `__schema`, `__type`, `__typename` |
| Operations | Query, Mutation, Subscription (via broadcasting) |

Continue with [Getting Started](getting-started.md).
