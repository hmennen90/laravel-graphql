# Spec conformance

The engine is hand-written (no `webonyx/graphql-php`), so its adherence to the GraphQL
specification is verified by an executable **conformance suite** rather than taken on
trust. It lives in `tests/Conformance/` and runs as its own PHPUnit suite:

```bash
./vendor/bin/phpunit --testsuite=Conformance
```

## What it covers

| Spec section | Examples verified |
|---|---|
| **Language** (lexer/parser) | block strings, comments, list/object literals, variable defaults, directives, full SDL type system + `extend`; malformed documents raise `SyntaxError` |
| **Validation** | field existence, leaf/composite selection sets, argument names/required/type, values-of-correct-type (incl. input objects), fragment type conditions, unknown/unused fragments, fragment cycles, lone anonymous operation, unique operation/fragment/variable names, variables-are-input-types, variable defined-and-used, variable-position compatibility, directive definition & locations, **overlapping-fields-can-be-merged** |
| **Execution** | scalar coercion (incl. `ID` always-string, `String`→`Int`), invalid enum → field error, null propagation to the nearest nullable ancestor, nullable vs non-null list items, aliases, `__typename`, interface/union resolution via inline fragments, fragment spreads, argument defaults, variable coercion & defaults, input-object coercion |
| **Introspection** | `__schema`/`__type`/`__typename`, type kinds, field/argument metadata, `@deprecated` on fields **and** enum values (`isDeprecated`/`deprecationReason`, hidden by default), interfaces/`possibleTypes`, directive introspection |

## Bugs it caught

Adding the suite immediately surfaced two real defects, now fixed:

- `@deprecated` on an SDL **field** was parsed but never applied to the field
  definition — introspection reported `isDeprecated: false`.
- `@deprecated` on an **enum value** had the same gap.

The validator otherwise passed every rule tested, including the notoriously tricky
overlapping-field-merge rule, fragment-cycle detection and variable-position
compatibility.

## Honest scope

This proves conformance for the exercised cases; it is not a byte-for-byte port of the
`graphql-js` reference suite. The core language, validation, execution and introspection
behaviour is verified — remaining risk is confined to long-tail edge cases (exotic
coercion corners, unusual directive locations). New cases are added as they surface;
if you hit a spec discrepancy, [open an issue](https://github.com/hmennen90/laravel-graphql/issues).
