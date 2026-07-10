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
5. **Find the gaps automatically.** Run `php artisan graphql:lint` — it scans the SDL
   and reports every directive this package does not support, with its location. Fix
   those (plain resolvers or a custom directive), then rewrite any custom Lighthouse
   PHP directives against `SchemaDirective` / `ArgumentDirective`
   (`make:graphql-directive` scaffolds one).
6. **Migrate feature tests.** Swap Lighthouse's `MakesGraphQLRequests` for this
   package's `Hmennen90\GraphQL\Testing\MakesGraphQLRequests` — the `$this->graphQL()`
   API is the same.
7. **Verify.** Run `php artisan graphql:validate`, then diff introspection — some
   *generated* type names (paginator/connection/filter enums) may differ, which clients
   can see.

## Directive compatibility

### Works as-is (same name & intent)

- CRUD & relations: `@all`, `@find`, `@first`, `@paginate`, `@hasMany`, `@hasOne`,
  `@belongsTo`, `@belongsToMany`, `@morphMany`, `@morphOne`, `@morphTo`, `@count`,
  `@create`, `@update`, `@delete`, `@upsert`, `@forceDelete`, `@restore`.
- Filtering & sorting: `@whereConditions`, `@orderBy`, and single-argument
  `@eq`, `@neq`, `@in`, `@notIn`, `@like`, `@whereBetween`, `@whereNull`, `@scope`,
  `@limit`.
- Auth, utility, search: `@guard`, `@can`, `@rename`, `@field`, `@inject`, `@search`,
  `@rules`, `@validator`, `@hash`, `@trim`, `@globalId`.

> Caveat: even for matching directives, generated type/enum names and default page
> sizes may differ from Lighthouse, so downstream clients may need small tweaks.

### Not supported yet — needs rework

| Lighthouse | Migration |
|---|---|
| `@whereHasConditions`, `@aggregate` | `@whereConditions` / a plain resolver |
| `@trashed` (soft-delete *filter*), `@with` / `@withCount`, `@builder`, `@spread`, `@drop` | plain resolver / query modifier |
| `@enum`, `@namespace`, Relay `@node` auto-node | declare explicitly in SDL / resolver |
| `@subscription` / `@broadcast` | use this package's subscription support (different wiring) |
| `@throttle`, `@complexity` | middleware / the configured depth & complexity limits |
| custom Lighthouse PHP directives | reimplement as `SchemaDirective`/`ArgumentDirective` |

## When it is (almost) painless

A schema built from the CRUD/relation/filter/sort directives above plus convention or
`@field` resolvers — the bulk of typical Lighthouse apps — migrates with little more
than config translation; `graphql:lint` tells you exactly what (if anything) is left. A
schema heavy on `@builder`/`@with`, Lighthouse subscriptions, or custom directives is a
larger job.

If a directive you rely on is missing, [open an issue](https://github.com/hmennen90/laravel-graphql/issues) —
the directive layer is designed to grow.
