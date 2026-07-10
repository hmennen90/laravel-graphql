# Migrating from Lighthouse

**Short answer: it is not a drop-in replacement, but a realistic migration.** The
directive *names and semantics* for the common CRUD core were deliberately kept
compatible with Lighthouse, so a schema that sticks to those directives moves over with
little change. Schemas that lean on Lighthouse's long-tail directives, its
convention-based resolver classes, custom PHP directives, or its subscriptions need
rework.

There is no automatic converter — plan a deliberate migration, not a package swap.

## Steps

1. **Swap the package**
   ```bash
   composer remove nuwave/lighthouse
   composer require hmennen90/laravel-graphql
   php artisan vendor:publish --tag=graphql-config
   ```
2. **Point at your SDL.** Set `graphql.schema.sdl_path` to your existing `.graphql`
   file(s). Both packages are SDL-first, so the schema text is largely reusable.
3. **Recreate config.** Translate `config/lighthouse.php` → `config/graphql.php`:
   model namespace (`lighthouse.namespaces.models` → `graphql.models.namespace`),
   pagination defaults, the route, and security limits.
4. **Resolver classes carry over.** Like Lighthouse, this package resolves
   `Query.foo`/`Mutation.foo` by convention to an invokable class **without a
   directive** — configure the namespaces (`graphql.namespaces.queries` /
   `.mutations`, default `App\GraphQL\Queries` / `App\GraphQL\Mutations`). The field
   name is StudlyCased (`latestPosts` → `LatestPosts`). Fields backed by directives
   (`@all`/`@create`) or a `@field(resolver:)` binding work too.
5. **Replace unsupported directives** (see below) with plain resolvers or a custom
   directive.
6. **Rewrite custom directives** against `SchemaDirective` / `ArgumentDirective`
   (`make:graphql-directive` scaffolds one).
7. **Verify.** Run `php artisan graphql:validate`, then diff introspection — some
   *generated* type names (paginator/connection/filter enums) may differ, which clients
   can see.

## Directive compatibility

### Works as-is (same name & intent)

`@all`, `@find`, `@first`, `@paginate`, `@hasMany`, `@hasOne`, `@belongsTo`,
`@belongsToMany`, `@morphMany`, `@morphOne`, `@morphTo`, `@count`, `@whereConditions`,
`@orderBy`, `@create`, `@update`, `@delete`, `@upsert`, `@guard`, `@can`, `@rename`,
`@field`, `@search`, `@rules`, `@validator`, `@hash`, `@trim`, `@globalId`, `@inject`.

> Caveat: even for matching directives, generated type/enum names and default page
> sizes may differ from Lighthouse, so downstream clients may need small tweaks.

### Not supported yet — needs rework

| Lighthouse | Migration |
|---|---|
| `@scope`, `@whereHasConditions`, single-field `@eq/@neq/@in/@like/@whereBetween/@whereNull` | use `@whereConditions`, or a plain resolver |
| `@trashed` / `@forceDelete` / `@restore` (soft deletes) | plain resolver or a custom directive |
| `@with` / `@withCount`, `@builder`, `@aggregate`, `@limit`, `@spread`, `@drop` | plain resolver / query modifier |
| `@enum`, `@namespace`, Relay `@node` auto-node | declare explicitly in SDL / resolver |
| `@subscription` / `@broadcast` | use this package's subscription support (different wiring) |
| `@throttle`, `@complexity` | middleware / the configured depth & complexity limits |
| custom Lighthouse PHP directives | reimplement as `SchemaDirective`/`ArgumentDirective` |

## When it is (almost) painless

A schema built from `@all/@find/@paginate/@hasMany/@whereConditions/@orderBy/@create…`
plus a few `@field` resolvers — the bulk of typical Lighthouse apps — migrates with
config translation and explicit resolver bindings. A schema heavy on soft deletes,
custom directives, `@scope`/`@builder`, or Lighthouse subscriptions is a larger job.

If a directive you rely on is missing, [open an issue](https://github.com/hmennen90/laravel-graphql/issues) —
the directive layer is designed to grow.
