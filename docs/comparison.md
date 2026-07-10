# Comparison with established packages

This package is **new and still evolving**. The tables below position it honestly
against the established options — they clarify *where it fits*, not that it is more
mature than long-standing projects.

!!! tip "Key differentiator — single source of truth"
    Unlike SDL-first stacks (e.g. Lighthouse), this package does **not** require you
    to implement a type twice. In a typical Lighthouse app the shape of an entity
    lives in **several places at once** — the SDL type, the Eloquent model, and
    often an extra PHP type/transformer or a pile of schema directives. Here a type
    is declared **once** (SDL *or* code-first *or* attributes) and resolvers are
    plain callables that read straight from your models or arrays. No SDL⇄model
    mirroring, no directive DSL to keep in sync.

## Feature matrix

| Feature | [nuwave/lighthouse](https://lighthouse-php.com) | [rebing/graphql-laravel](https://github.com/rebing/graphql-laravel) | [webonyx/graphql-php](https://webonyx.github.io/graphql-php/) | **hmennen90/laravel-graphql** |
|---|---|---|---|---|
| Primary approach | SDL-first | code-first (classes) | code-first (library) | **hybrid: SDL + code-first + attributes** |
| Single source of truth | ❌ SDL + model (+ directives) | ⚠️ class per type | n/a | ✅ define a type once |
| Generate types from models/requests/JSON | ⚠️ some directives | ❌ | n/a | ✅ model + FormRequest + response generators |
| GraphQL engine | webonyx | webonyx | is the engine | **own, hand-written** |
| Extra runtime deps | webonyx + more | webonyx | — | **none beyond illuminate** |
| Laravel integration | ✅ first-class | ✅ first-class | ❌ | ✅ first-class |
| Learning surface | schema directive DSL | base classes | engine API | plain callables |
| Introspection | ✅ | ✅ | ✅ | ✅ |
| Query / mutation | ✅ | ✅ | ✅ | ✅ |
| Subscriptions — transport | broadcasting (Pusher/Echo) | broadcasting (limited) | n/a | broadcasting (Reverb/Pusher) **+ graphql-ws + SSE** |
| Subscriptions — maturity | mature, battle-tested | basic | n/a | new |
| Query batching | ✅ | ✅ | n/a | ✅ |
| N+1 batching (DataLoader) | ✅ | ⚠️ | ✅ (deferred) | ✅ built-in DataLoader |
| Depth / complexity limits | ✅ | ⚠️ | ✅ | ✅ configurable |
| GraphiQL | ✅ (plugin) | ✅ | n/a | ✅ bundled |
| Validation rules | comprehensive | via webonyx | comprehensive | comprehensive spec set |
| Custom directives | ✅ many | ⚠️ some | via engine | ✅ runtime + SDL build-time |
| Eloquent CRUD directives | ✅✅ (`@all/@find/@paginate/@create`…) | ❌ | n/a | ✅ `@all/@find/@first/@paginate/@create/@update/@delete/@upsert` |
| Relation directives | ✅ | ❌ | n/a | ✅ `@hasMany/@hasOne/@belongsTo/@belongsToMany/@count` |
| Filtering / sorting directives | ✅ `@whereConditions/@orderBy` + single-field | ❌ | n/a | ✅ `@whereConditions/@orderBy` + `@eq/@neq/@in/@like/@whereBetween/@whereNull/@scope/@limit` |
| Soft-delete directives | `@trashed/@forceDelete/@restore` | ❌ | n/a | ✅ `@forceDelete/@restore` |
| Test helper trait | ✅ `MakesGraphQLRequests` | ❌ | n/a | ✅ `MakesGraphQLRequests` |
| SDL type extensions (`extend`) | ✅ | n/a | ✅ | ✅ object/interface/input |
| Schema self-validation | ✅ | ✅ | ✅ | ✅ |
| `@oneOf` / `@specifiedBy` | ⚠️ partial | — | ✅ | ✅ |
| Field authorization | `@can` directive | method-based | manual | Gate via context **and** `@can` directive |
| Argument validation | `@rules` directive | Laravel rules | manual | resolver **and** `@rules`/`@validator` directives |
| Argument sanitisers | `@trim`/`@hash`/`@spread` | — | — | `@trim`/`@hash`/`@globalId` |
| Full-text search | `@search` (Scout) | ❌ | n/a | ✅ `@search` (Scout) |
| Code-first attribute directives | ❌ (SDL only) | n/a | n/a | ✅ `#[All]`/`#[Paginate]`… (same impl. as SDL) |
| Federation | ✅ v2 | ❌ | plugin | ✅ **v2 subgraph** (`@key/@shareable/@requires`, `_service`/`_entities`) |
| Performance harness | — | — | — | ✅ `composer bench` (parse/build/validate/execute) |
| Artisan commands | print/validate/cache/generators | some | n/a | print/validate/**lint**/cache/clear + `make:graphql-*` |
| Schema caching | ✅ (`lighthouse:cache`) | ⚠️ | n/a | ✅ AST cache (`graphql:cache`) |
| File uploads (multipart) | ✅ | ✅ | n/a | ✅ `Upload` scalar |
| Persisted queries (APQ) | ✅ | ⚠️ | n/a | ✅ Apollo APQ |
| Relay pagination | ✅ | ⚠️ | manual | ✅ connection helpers |
| HTTP caching | `@cacheControl` | ⚠️ | manual | ✅ `@cacheControl` + Cache-Control |
| Minimum PHP | 8.1+ | 8.0+ | 7.4 / 8+ | **8.4+** |
| Static analysis | — | — | partial | **PHPStan level 10** |
| Correctness verification | — | — | reference test suite | spec conformance + fuzz + **Infection** (100% mut. coverage, ~79% MSI) |
| Maturity | mature, large community | mature | mature (de-facto engine) | **new (1.0), small community** |

## Every difference, by package

### vs. nuwave/lighthouse (SDL-first)

- **No double implementation.** Lighthouse: the entity shape lives in the SDL type
  *and* the Eloquent model *and* often a transformer/type class. Here: one
  declaration, resolvers read your model directly.
- **Directives *or* plain callables.** Lighthouse forces its schema-directive DSL.
  Here you can use the same style — `@all`, `@find`, `@paginate`, `@hasMany`,
  `@whereConditions`, `@create`… over Eloquent — *or* drop to plain PHP resolver
  callables, whichever fits. No lock-in to a DSL.
- **Own engine.** Lighthouse runs on `webonyx/graphql-php`; this ships its own
  lexer/parser/validator/executor, so there is one fewer third-party dependency.
- **Schema styles.** Lighthouse is SDL-only; this also supports code-first and
  attribute-driven schemas that compile to the same internal schema — and every
  Eloquent directive has a `#[All]`/`#[Paginate]` attribute equivalent that runs the
  same implementation.
- **Performance.** Against `webonyx/graphql-php` (Lighthouse's engine) this engine is
  faster across the board — many times faster on parse/validate/small executions and
  ~1.8–2× faster on large lists after the executor rewrite. End-to-end vs Lighthouse
  (Laravel + Eloquent, 200 rows) it resolves ~1.5× faster for `@all` and ~3.7× faster
  for `@all+@eq` and `@paginate+@eq`. See [Benchmarks](benchmarks.md).
- **Trade-off.** The directive catalogues are now comparable — read, relations,
  filter/sort, nested mutations, Scout, validation/sanitiser directives and Apollo
  Federation are all covered here. Lighthouse still leads on maturity, subscription
  transports and community size; this package is younger.

### vs. rebing/graphql-laravel (code-first)

- **Less boilerplate.** rebing requires a class per Type/Query/Mutation extending
  framework base classes. Here you can define fields inline, via closures, via a
  fluent builder, via attributes, *or* via SDL.
- **Own engine** vs. webonyx underneath rebing.
- **Hybrid** — you are not locked into code-first; mix SDL and attributes freely.
- **Trade-off.** rebing is mature and widely used; its class structure can be a plus
  for very large schemas.

### vs. webonyx/graphql-php (engine only)

- **Framework integration.** webonyx is framework-agnostic — you wire the HTTP
  endpoint, config, error handling, GraphiQL and subscriptions yourself. This ships
  all of that for Laravel out of the box.
- **This is not a wrapper.** It does not depend on webonyx; the engine is a separate
  hand-written implementation of the spec algorithms.
- **Trade-off.** webonyx is the de-facto, battle-tested engine with the most
  exhaustive spec-edge coverage. This engine implements the comprehensive validation
  rule set (verified by a spec conformance suite — language, validation, execution,
  introspection), a broad built-in directive catalogue and a benchmark harness, but has
  far less production mileage.

## When to choose which

- **This package** — you want one dependency-light package that avoids SDL⇄model
  duplication, lets you pick SDL / code-first / attributes per type, and runs on a
  modern PHP 8.4 / PHPStan level 10 codebase; and you can live with a young project.
- **Lighthouse** — you want the largest ecosystem, a rich directive toolkit and the
  most mature realtime subscriptions, and you are comfortable with SDL-first.
- **rebing/graphql-laravel** — you prefer a mature, class-based code-first approach.
- **webonyx/graphql-php** — you need the engine outside Laravel.
