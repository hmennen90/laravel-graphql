# Security Policy

## Supported versions

The package is pre-1.0; security fixes target the latest `main` and the most recent
released tag.

## Reporting a vulnerability

**Please do not open public issues for security vulnerabilities.**

Report them privately via GitHub's
[private vulnerability reporting](https://github.com/hmennen90/laravel-graphql/security/advisories/new)
or by email to **hmennen90@gmail.com**.

Include:

- a description of the issue and its impact,
- a minimal reproduction (schema, query, configuration),
- affected version(s).

You will receive an acknowledgement within a few days. Once a fix is available, a
patched release and an advisory will be published with credit to the reporter
(unless anonymity is requested).

## Scope notes

- Enable `graphql.security.max_depth` / `max_complexity` in production to mitigate
  denial-of-service via deeply nested or overly broad queries.
- Keep `graphql.debug = false` in production so internal exception messages are masked.
