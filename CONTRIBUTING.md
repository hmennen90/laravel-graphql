# Contributing

Thanks for your interest in improving **laravel-graphql**! Contributions of all
kinds are welcome — bug reports, documentation, tests and features.

## Getting started

```bash
git clone https://github.com/hmennen90/laravel-graphql.git
cd laravel-graphql
composer install
```

## Development workflow

- **Test-driven:** write a failing test first, then the implementation.
- Keep the engine (`src/Engine/**`) free of any `Illuminate\*` dependency — this is
  enforced by `EnginePurityTest`.
- Before pushing, both must be green:

```bash
composer test       # PHPUnit (Unit + Feature)
composer analyse    # PHPStan level 10
```

New code must pass **PHPStan level 10** with no ignores, casts-to-silence, or
baseline entries. Match the surrounding code style (PSR-12, typed, `final` where
reasonable).

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/). Examples:

```
feat(engine): add @stream directive support
fix(executor): correct null bubbling in nested lists
docs: document persisted queries
```

Releases and the changelog are generated automatically by semantic-release, so the
commit type (`feat`, `fix`, `docs`, `chore`, …) determines the next version.

## Pull requests

1. Fork and create a topic branch off `main`.
2. Add tests and keep `composer test` / `composer analyse` green.
3. Update the docs (`docs/`) and README where relevant.
4. Open a PR describing the change and linking any related issue.

## Reporting bugs

Please use the issue templates and include a minimal reproduction (schema + query +
expected vs actual result). See also [SECURITY.md](SECURITY.md) for security issues.

By contributing you agree that your contributions are licensed under the project's
[MIT License](LICENSE).
