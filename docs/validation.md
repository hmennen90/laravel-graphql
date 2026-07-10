# Validation & argument sanitisers

Directives placed on a field *argument* run before the resolver — they transform the
incoming value (sanitisers) or reject it (validation). They compose freely with the
Eloquent directives (`@create`, `@update`, …) on the same field, always running first.

| Directive | Kind | Effect |
|---|---|---|
| `@rules(apply: [...])` | validation | validate one argument with Laravel rules |
| `@validator(class:)` | validation | validate all arguments via a class exposing `rules()` |
| `@trim` | sanitiser | strip leading/trailing whitespace from a string argument |
| `@hash` | sanitiser | bcrypt-hash a string argument (e.g. a password) |
| `@globalId` | sanitiser | decode a Relay global id (`base64("Type:id")`) to its raw key |

## `@rules` — per-argument validation

Validate a single argument with any Laravel validation rules. A failure throws a
`ValidationException`, which the HTTP layer renders as a GraphQL error under
`errors[].extensions.validation`.

```graphql
type Mutation {
  register(
    email: String @rules(apply: ["required", "email"])
    age: Int @rules(apply: ["integer", "min:18"])
  ): User @create
}
```

## `@validator` — validate the whole field

Bind a dedicated validator class that validates all of the field's arguments at once.
The class must expose `rules(): array` and may expose `messages(): array`; it is
resolved through the container, so you can type-hint dependencies.

```graphql
type Mutation {
  updatePost(id: ID!, title: String, body: String): Post
    @update
    @validator(class: "App\\GraphQL\\Validators\\UpdatePostValidator")
}
```

```php
namespace App\GraphQL\Validators;

final class UpdatePostValidator
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body'  => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['title.max' => 'Keep the title short.'];
    }
}
```

## Sanitisers

Sanitisers rewrite the argument value in place before it reaches the resolver.

```graphql
type Mutation {
  createUser(
    name: String @trim
    password: String @hash
  ): User @create

  # decode a Relay global id back to the raw database key
  post(id: ID @globalId): Post @find
}
```

- `@trim` — trims surrounding whitespace on string values (non-strings pass through).
- `@hash` — replaces the value with its bcrypt hash (via `Hash::make`).
- `@globalId` — decodes `base64("Type:id")` down to `id`; use
  `Relay::toGlobalId()` / `Relay::fromGlobalId()` for the encode/decode helpers.

## Writing your own argument directive

Implement `ArgumentDirective`; the returned closure receives the argument value and
returns the transformed value (or throws to reject it). Register it in the
`DirectiveRegistry` like any other directive.

```php
use Hmennen90\GraphQL\Engine\Building\SchemaFirst\ArgumentDirective;

final readonly class UppercaseDirective implements ArgumentDirective
{
    public function applyToArgument($argument, $node, $context): \Closure
    {
        return static fn (mixed $value): mixed => is_string($value) ? strtoupper($value) : $value;
    }
}
```
