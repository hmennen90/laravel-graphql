# Comparison with established packages

This package is **new and still evolving**. The table below is an honest
positioning against the established options — it is meant to clarify *where this
package fits*, not to claim it is more mature than long-standing projects.

| Feature | [nuwave/lighthouse](https://lighthouse-php.com) | [rebing/graphql-laravel](https://github.com/rebing/graphql-laravel) | [webonyx/graphql-php](https://webonyx.github.io/graphql-php/) | **hmennen90/laravel-graphql** |
|---|---|---|---|---|
| Primary approach | SDL-first | Code-first | Code-first (library) | **Hybrid: SDL + code-first + attributes** |
| GraphQL engine | webonyx | webonyx | is the engine | **own, hand-written** |
| Laravel integration | ✅ first-class | ✅ first-class | ❌ (framework-agnostic) | ✅ first-class |
| Introspection | ✅ | ✅ | ✅ | ✅ |
| Query / mutation | ✅ | ✅ | ✅ | ✅ |
| Subscriptions | ✅ full (WebSockets) | ⚠️ limited | n/a | ⚠️ engine seam (v1) |
| Validation rules | full (via webonyx) | full (via webonyx) | full | core subset |
| Directives | many built-in + custom | some | `@skip/@include/@deprecated` + custom | `@skip/@include/@deprecated` |
| Query batching | ✅ | ✅ | n/a | ✅ |
| GraphiQL | ✅ (playground plugin) | ✅ | n/a | ✅ bundled |
| Minimum PHP | 8.1+ | 8.0+ | 7.4 / 8+ | **8.4+** |
| Static analysis | — | — | partial | **PHPStan level 10** |
| Maturity | mature, large community | mature | mature (de-facto engine) | **new / experimental** |

## When to pick this package

- You want **one package** that supports SDL, code-first *and* attribute-driven
  schemas without swapping libraries.
- You value a **self-contained, dependency-light** engine (no `webonyx/graphql-php`)
  and a modern PHP 8.4 / PHPStan level 10 codebase.
- You are comfortable on a young project and can live with the current v1 scope
  (subscription transport and the full validation-rule set are still growing).

## When to pick an established package

- You need **battle-tested** production coverage today, a large ecosystem, or
  full realtime subscriptions — reach for **Lighthouse** (SDL) or
  **rebing/graphql-laravel** (code-first).
- You only need the **engine** outside Laravel — use **webonyx/graphql-php**.
